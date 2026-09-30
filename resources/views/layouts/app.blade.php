<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="mobile-main">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
