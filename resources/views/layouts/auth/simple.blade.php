<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 bg-[radial-gradient(circle_at_top,_rgba(16,185,129,0.12),_transparent_38%)] p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-4 rounded-2xl border border-zinc-200/80 bg-white/90 p-7 shadow-xl shadow-emerald-950/5 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/90">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="flex h-9 w-9 mb-1 items-center justify-center rounded-md">
                        <x-app-logo-icon class="size-10 text-emerald-700 dark:text-emerald-400" />
                    </span>
                    <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ config('app.display_name') }}</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
