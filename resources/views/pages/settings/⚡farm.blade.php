<?php

use App\Models\FarmSetting;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Farm settings')] class extends Component {
    public int $eggsPerTray = 30;

    public function mount(): void
    {
        $this->ensureAuthenticated();

        $settings = FarmSetting::query()->firstOrCreate(
            ['id' => 1],
            ['eggs_per_tray' => 30],
        );

        $this->eggsPerTray = $settings->eggs_per_tray;
    }

    public function updateFarmSettings(): void
    {
        $this->ensureAuthenticated();

        $validated = $this->validate([
            'eggsPerTray' => ['required', 'integer', 'between:1,1000'],
        ]);

        FarmSetting::query()->updateOrCreate(
            ['id' => 1],
            ['eggs_per_tray' => $validated['eggsPerTray']],
        );

        Flux::toast(variant: 'success', text: __('Farm settings updated.'));
    }

    private function ensureAuthenticated(): void
    {
        abort_unless(Auth::check(), 403);
    }
};
?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Farm settings') }}</flux:heading>

    <x-pages::settings.layout
        :heading="__('Farm')"
        :subheading="__('Configure values used across egg inventory and sales')"
    >
        <form wire:submit="updateFarmSettings" class="my-6 w-full space-y-6">
            <flux:input
                wire:model="eggsPerTray"
                :label="__('Eggs per tray')"
                :description="__('Used to convert tray sales into individual eggs. Existing sales keep their saved egg quantity.')"
                type="number"
                min="1"
                max="1000"
                required
            />

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit" data-test="save-farm-settings">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </form>
    </x-pages::settings.layout>
</section>
