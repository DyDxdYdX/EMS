<?php

namespace App\Support;

class PdfDocument
{
    private const float PAGE_WIDTH = 595.0;

    private const float PAGE_HEIGHT = 842.0;

    private const float MARGIN = 48.0;

    /** @var list<string> */
    private array $pages = [];

    private string $currentPage = '';

    private float $cursorY = 0.0;

    private int $pageNumber = 0;

    public function __construct()
    {
        $this->startPage();
    }

    public function heading(string $text): void
    {
        $this->writeLine($text, 16, true, 22);
    }

    public function subheading(string $text): void
    {
        $this->writeLine($text, 12, true, 18);
    }

    public function line(string $text): void
    {
        $this->writeLine($text, 11, false, 15);
    }

    public function pair(string $label, string $value): void
    {
        $this->ensureSpace(15);
        $this->paint($label, self::MARGIN, $this->cursorY, 11, false);
        $this->paint($value, 360, $this->cursorY, 11, false);
        $this->cursorY -= 15;
    }

    /**
     * @param  list<string>  $cells
     * @param  list<float>  $widths
     */
    public function row(array $cells, array $widths, bool $bold = false): void
    {
        $wrappedColumns = [];
        $lineCount = 1;

        foreach ($cells as $index => $cell) {
            $lines = $this->wrap($cell, $widths[$index] ?? 120.0, 11);
            $wrappedColumns[] = $lines;
            $lineCount = max($lineCount, count($lines));
        }

        $this->ensureSpace($lineCount * 15);

        for ($line = 0; $line < $lineCount; $line++) {
            $x = self::MARGIN;

            foreach ($wrappedColumns as $index => $lines) {
                $this->paint($lines[$line] ?? '', $x, $this->cursorY, 11, $bold);
                $x += $widths[$index] ?? 120.0;
            }

            $this->cursorY -= 15;
        }
    }

    public function spacer(): void
    {
        $this->ensureSpace(12);
        $this->cursorY -= 12;
    }

    public function render(): string
    {
        $this->finishPage();

        if ($this->pages === []) {
            $this->startPage();
            $this->finishPage();
        }

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];

        $pageIds = [];
        $nextId = 5;

        foreach ($this->pages as $content) {
            $pageId = $nextId++;
            $contentId = $nextId++;
            $pageIds[] = $pageId;
            $objects[$pageId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.0F %.0F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $contentId,
            );
            $objects[$contentId] = '<< /Length '.strlen($content)." >>\nstream\n{$content}endstream";
        }

        $kids = implode(' ', array_map(fn (int $id): string => $id.' 0 R', $pageIds));
        $objects[2] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', $kids, count($pageIds));
        ksort($objects);

        $binary = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($binary);
            $binary .= $id." 0 obj\n".$body."\nendobj\n";
        }

        $size = max(array_keys($objects)) + 1;
        $startxref = strlen($binary);
        $binary .= 'xref'."\n0 {$size}\n";
        $binary .= "0000000000 65535 f \n";

        for ($id = 1; $id < $size; $id++) {
            $binary .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }

        return $binary.'trailer'."\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$startxref}\n%%EOF\n";
    }

    public static function escape(string $text): string
    {
        $latin = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');

        return strtr($latin, [
            '\\' => '\\\\',
            '(' => '\\(',
            ')' => '\\)',
            "\r" => '\\r',
            "\n" => '\\n',
        ]);
    }

    private function writeLine(string $text, float $size, bool $bold, float $leading): void
    {
        $width = self::PAGE_WIDTH - (self::MARGIN * 2);

        foreach ($this->wrap($text, $width, $size) as $line) {
            $this->ensureSpace($leading);
            $this->paint($line, self::MARGIN, $this->cursorY, $size, $bold);
            $this->cursorY -= $leading;
        }
    }

    private function paint(string $text, float $x, float $y, float $size, bool $bold): void
    {
        $font = $bold ? 'F2' : 'F1';
        $this->currentPage .= sprintf(
            "BT /%s %.1F Tf 1 0 0 1 %.2F %.2F Tm %s Tj ET\n",
            $font,
            $size,
            $x,
            $y,
            '('.self::escape($text).')',
        );
    }

    private function ensureSpace(float $needed): void
    {
        if ($this->cursorY - $needed < self::MARGIN) {
            $this->startPage();
        }
    }

    private function startPage(): void
    {
        if ($this->currentPage !== '' || $this->pageNumber === 0) {
            if ($this->pageNumber > 0) {
                $this->finishPage();
            }

            $this->pageNumber++;
            $this->currentPage = '';
            $this->cursorY = self::PAGE_HEIGHT - self::MARGIN;
        }
    }

    private function finishPage(): void
    {
        if ($this->pageNumber === 0) {
            return;
        }

        $this->paint('Page '.$this->pageNumber, self::MARGIN, 28, 9, false);
        $this->pages[] = $this->currentPage;
        $this->currentPage = '';
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, float $width, float $size): array
    {
        $maxCharacters = max(1, (int) floor($width / ($size * 0.5)));

        if (mb_strlen($text) <= $maxCharacters) {
            return [$text];
        }

        $lines = [];
        $remaining = $text;

        while ($remaining !== '') {
            if (mb_strlen($remaining) <= $maxCharacters) {
                $lines[] = $remaining;
                break;
            }

            $chunk = mb_substr($remaining, 0, $maxCharacters);
            $space = mb_strrpos($chunk, ' ');

            if ($space !== false && $space > 0) {
                $lines[] = mb_substr($remaining, 0, $space);
                $remaining = ltrim(mb_substr($remaining, $space + 1));

                continue;
            }

            $lines[] = $chunk;
            $remaining = mb_substr($remaining, $maxCharacters);
        }

        return $lines;
    }
}
