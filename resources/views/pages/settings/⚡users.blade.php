<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Users')] class extends Component {
    use PasswordValidationRules, ProfileValidationRules, WithPagination;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /** @return LengthAwarePaginator<int, User> */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()->orderBy('name')->orderBy('id')->paginate(15);
    }

    public function addUser(): void
    {
        abort_unless(Auth::check(), 403);

        $this->email = Str::lower(trim($this->email));

        try {
            $validated = $this->validate([
                ...$this->profileRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $exception) {
            $this->reset('password', 'password_confirmation');

            throw $exception;
        }

        User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $this->reset('name', 'email', 'password', 'password_confirmation');
        $this->resetPage();
        unset($this->users);
        Flux::modal('user-form')->close();
        Flux::toast(variant: 'success', text: __('User added.'));
    }

    public function cancelAdding(): void
    {
        $this->reset('name', 'email', 'password', 'password_confirmation');
        $this->resetValidation();
        Flux::modal('user-form')->close();
    }
};
?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Users') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Users')" :subheading="__('Add people who can sign in and use the farm system')">
        <div class="my-6 flex justify-end">
            <flux:modal.trigger name="user-form">
                <flux:button variant="primary" icon="plus">{{ __('Add user') }}</flux:button>
            </flux:modal.trigger>
        </div>

        <flux:card class="data-panel space-y-3">
            @foreach ($this->users as $user)
                <div wire:key="user-{{ $user->id }}" class="flex items-center gap-3 border-b border-zinc-200 py-2 last:border-b-0 dark:border-zinc-700">
                    <flux:avatar :name="$user->name" :initials="$user->initials()" />
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">{{ $user->name }}</p>
                        <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $user->email }}</p>
                    </div>
                </div>
            @endforeach
            {{ $this->users->links() }}
        </flux:card>
    </x-pages::settings.layout>

    <flux:modal name="user-form" class="max-w-xl" @close="$wire.cancelAdding()">
        <form wire:submit="addUser" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Add user') }}</flux:heading>
                <flux:subheading>{{ __('This user will have the same access as everyone else. Share their password privately.') }}</flux:subheading>
            </div>

            <flux:input wire:model="name" :label="__('Name')" type="text" maxlength="255" required autocomplete="off" />
            <flux:input wire:model="email" :label="__('Email')" type="email" maxlength="255" required autocomplete="off" />
            <flux:input wire:model="password" :label="__('Password')" type="password" required autocomplete="new-password" viewable />
            <flux:input wire:model="password_confirmation" :label="__('Confirm password')" type="password" required autocomplete="new-password" viewable />

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="cancelAdding">{{ __('Cancel') }}</flux:button>
                <flux:button class="action-button" variant="primary" type="submit" data-test="save-user">{{ __('Add user') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
