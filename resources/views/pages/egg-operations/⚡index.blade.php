<?php

use App\Models\EggGrading;
use App\Models\Sale;
use App\Models\StockAdjustment;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Egg operations')] class extends Component {
    #[Url]
    public string $tab = 'production';

    public bool $showGradingPrompt = false;

    public function mount(): void
    {
        if (! in_array($this->tab, ['production', 'grading', 'adjustments'], true)) {
            $this->tab = 'production';
        }
    }

    #[Computed]
    public function availableToGrade(): int
    {
        return max(0, EggGrading::availableEggQuantity());
    }

    #[Computed]
    public function stockOnHand(): int
    {
        return (int) EggGrading::query()->sum('quantity')
            + (int) StockAdjustment::query()->where('type', 'add')->sum('quantity')
            - (int) Sale::query()->sum('normalized_egg_quantity')
            - (int) StockAdjustment::query()->where('type', 'remove')->sum('quantity');
    }

    #[On('egg-operations-changed')]
    public function refreshOverview(bool $showGradingPrompt = false): void
    {
        unset($this->availableToGrade, $this->stockOnHand);
        $this->showGradingPrompt = $showGradingPrompt;
    }
};
?>

<section class="page-shell">
    <div class="page-header">
        <div>
            <flux:heading size="xl" level="1">{{ __('Egg operations') }}</flux:heading>
            <flux:subheading>{{ __('Record the daily collection, grade available eggs, and keep stock accurate—all in one place.') }}</flux:subheading>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <flux:card class="metric-card space-y-2">
            <flux:text>{{ __('Available to grade') }}</flux:text>
            <flux:heading size="xl" data-test="egg-operations-available-to-grade">{{ number_format($this->availableToGrade) }}</flux:heading>
            <flux:text class="text-xs">{{ __('Collected eggs not yet assigned a grade') }}</flux:text>
        </flux:card>
        <flux:card class="metric-card space-y-2">
            <flux:text>{{ __('Graded stock on hand') }}</flux:text>
            <flux:heading size="xl" data-test="egg-operations-stock-on-hand">{{ number_format($this->stockOnHand) }}</flux:heading>
            <flux:text class="text-xs">{{ __('After sales and stock adjustments') }}</flux:text>
        </flux:card>
    </div>

    @if ($showGradingPrompt && $tab === 'production')
        <flux:callout icon="check-circle" color="green" data-test="grading-next-step">
            <flux:callout.heading>{{ __('Production recorded') }}</flux:callout.heading>
            <flux:callout.text>{{ __('When you are ready, record grading for the eggs now available.') }}</flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" :href="route('egg-operations.index', ['tab' => 'grading'])" wire:navigate>{{ __('Grade eggs') }}</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <nav aria-label="{{ __('Egg operation sections') }}" class="flex gap-2 overflow-x-auto border-b border-zinc-200 pb-2 dark:border-zinc-700">
        <a href="{{ route('egg-operations.index', ['tab' => 'production']) }}" wire:navigate @if ($tab === 'production') aria-current="page" @endif data-test="egg-tab-production" @class([
            'shrink-0 rounded-lg px-4 py-2.5 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600',
            'bg-emerald-700 text-white dark:bg-emerald-500 dark:text-zinc-950' => $tab === 'production',
            'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white' => $tab !== 'production',
        ])>{{ __('Production') }}</a>
        <a href="{{ route('egg-operations.index', ['tab' => 'grading']) }}" wire:navigate @if ($tab === 'grading') aria-current="page" @endif data-test="egg-tab-grading" @class([
            'shrink-0 rounded-lg px-4 py-2.5 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600',
            'bg-emerald-700 text-white dark:bg-emerald-500 dark:text-zinc-950' => $tab === 'grading',
            'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white' => $tab !== 'grading',
        ])>{{ __('Grading') }}</a>
        <a href="{{ route('egg-operations.index', ['tab' => 'adjustments']) }}" wire:navigate @if ($tab === 'adjustments') aria-current="page" @endif data-test="egg-tab-adjustments" @class([
            'shrink-0 rounded-lg px-4 py-2.5 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600',
            'bg-emerald-700 text-white dark:bg-emerald-500 dark:text-zinc-950' => $tab === 'adjustments',
            'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white' => $tab !== 'adjustments',
        ])>{{ __('Stock adjustments') }}</a>
    </nav>

    @if ($tab === 'grading')
        <livewire:pages::gradings.index :embedded="true" />
    @elseif ($tab === 'adjustments')
        <livewire:pages::stock-adjustments.index :embedded="true" />
    @else
        <livewire:pages::productions.index :embedded="true" />
    @endif
</section>
