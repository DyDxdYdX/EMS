<div x-data="{ moreOpen: false }" class="lg:hidden">
    <div x-cloak x-show="moreOpen" x-transition.opacity class="fixed inset-0 z-20 bg-zinc-950/40" @click="moreOpen = false" aria-hidden="true"></div>

    <div id="mobile-more-menu" x-cloak x-show="moreOpen" x-transition class="mobile-more-panel fixed inset-x-0 z-30 rounded-t-2xl border border-zinc-200 bg-white p-4 shadow-2xl dark:border-zinc-700 dark:bg-zinc-900" @keydown.escape.window="if (moreOpen) { moreOpen = false; $refs.moreToggle.focus() }">
        <div class="mb-3 flex items-center justify-between">
            <flux:heading size="lg">{{ __('More') }}</flux:heading>
            <button x-ref="moreClose" type="button" class="rounded-lg p-2 text-zinc-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:text-zinc-300" @click="moreOpen = false; $refs.moreToggle.focus()" aria-label="{{ __('Close menu') }}">
                <flux:icon name="x-mark" class="size-5" />
            </button>
        </div>
        <nav aria-label="{{ __('More pages') }}" class="grid grid-cols-2 gap-2">
            <a href="{{ route('reports.index') }}" wire:navigate @click="moreOpen = false" class="mobile-more-link"><flux:icon name="chart-bar" class="size-5" />{{ __('Reports') }}</a>
            <a href="{{ route('customers.index') }}" wire:navigate @click="moreOpen = false" class="mobile-more-link"><flux:icon name="users" class="size-5" />{{ __('Customers') }}</a>
            <a href="{{ route('egg-grades.index') }}" wire:navigate @click="moreOpen = false" class="mobile-more-link"><flux:icon name="scale" class="size-5" />{{ __('Egg grades') }}</a>
            <a href="{{ route('expense-categories.index') }}" wire:navigate @click="moreOpen = false" class="mobile-more-link"><flux:icon name="tag" class="size-5" />{{ __('Expense categories') }}</a>
            <a href="{{ route('farm-settings.edit') }}" wire:navigate @click="moreOpen = false" class="mobile-more-link"><flux:icon name="cog-6-tooth" class="size-5" />{{ __('Farm settings') }}</a>
            <a href="{{ route('profile.edit') }}" wire:navigate @click="moreOpen = false" class="mobile-more-link"><flux:icon name="user-circle" class="size-5" />{{ __('Profile') }}</a>
        </nav>
    </div>

    <nav aria-label="{{ __('Main navigation') }}" class="mobile-bottom-nav fixed inset-x-0 bottom-0 z-30 grid grid-cols-5 border-t border-zinc-200 bg-white/95 px-1 pt-1 shadow-[0_-8px_24px_rgba(0,0,0,0.06)] backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95">
        <a href="{{ route('dashboard') }}" wire:navigate @if (request()->routeIs('dashboard')) aria-current="page" @endif @class(['mobile-nav-link', 'mobile-nav-link-active' => request()->routeIs('dashboard')])>
            <flux:icon name="home" class="size-5" /><span>{{ __('Home') }}</span>
        </a>
        <a href="{{ route('egg-operations.index') }}" wire:navigate @if (request()->routeIs('egg-operations.*')) aria-current="page" @endif @class(['mobile-nav-link', 'mobile-nav-link-active' => request()->routeIs('egg-operations.*')])>
            <flux:icon name="clipboard-document-list" class="size-5" /><span>{{ __('Eggs') }}</span>
        </a>
        <a href="{{ route('sales.index') }}" wire:navigate @if (request()->routeIs('sales.*')) aria-current="page" @endif @class(['mobile-nav-link', 'mobile-nav-link-active' => request()->routeIs('sales.*')])>
            <flux:icon name="shopping-cart" class="size-5" /><span>{{ __('Sales') }}</span>
        </a>
        <a href="{{ route('expenses.index') }}" wire:navigate @if (request()->routeIs('expenses.*')) aria-current="page" @endif @class(['mobile-nav-link', 'mobile-nav-link-active' => request()->routeIs('expenses.*')])>
            <flux:icon name="receipt-percent" class="size-5" /><span>{{ __('Expenses') }}</span>
        </a>
        <button x-ref="moreToggle" type="button" @click="moreOpen = ! moreOpen; if (moreOpen) $nextTick(() => $refs.moreClose.focus())" :aria-expanded="moreOpen.toString()" aria-controls="mobile-more-menu" @class(['mobile-nav-link', 'mobile-nav-link-active' => request()->routeIs('reports.*', 'customers.*', 'egg-grades.*', 'expense-categories.*', 'farm-settings.*', 'profile.*')])>
            <flux:icon name="ellipsis-horizontal" class="size-5" /><span>{{ __('More') }}</span>
        </button>
    </nav>
</div>
