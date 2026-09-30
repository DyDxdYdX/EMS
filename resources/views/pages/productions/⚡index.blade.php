<?php

use App\Models\EggGrading;
use App\Models\Production;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Daily production')] class extends Component {
    use WithPagination;

    public bool $embedded = false;

    #[Locked]
    public ?int $editingProductionId = null;

    #[Locked]
    public ?int $productionPendingDeletionId = null;

    public string $productionDate = '';
    public int $totalEggs = 0;
    public int $damagedEggs = 0;
    public string $notes = '';
    public string $search = '';

    public function mount(bool $embedded = false): void
    {
        $this->embedded = $embedded;
        $this->productionDate = now()->toDateString();
    }

    /** @return LengthAwarePaginator<int, Production> */
    #[Computed]
    public function productions(): LengthAwarePaginator
    {
        return Production::query()
            ->when(filled($this->search), fn (Builder $query): Builder => $query->where('notes', 'like', '%'.$this->search.'%'))
            ->orderByDesc('production_date')
            ->orderByDesc('id')
            ->paginate(15);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        unset($this->productions);
    }

    #[Computed]
    public function expectedGradableEggs(): int
    {
        return max(0, $this->totalEggs - $this->damagedEggs);
    }

    public function saveProduction(): void
    {
        $this->ensureAuthenticated();

        $validated = $this->validate($this->productionRules());
        $attributes = [
            'production_date' => $validated['productionDate'],
            'total_eggs' => $validated['totalEggs'],
            'damaged_eggs' => $validated['damagedEggs'],
            'notes' => filled($validated['notes']) ? $validated['notes'] : null,
        ];
        $production = $this->editingProductionId === null
            ? null
            : Production::query()->findOrFail($this->editingProductionId);

        $this->ensureEnoughEggsRemainForGrading(
            $production,
            (int) $validated['totalEggs'],
            (int) $validated['damagedEggs'],
            'totalEggs',
        );

        $wasCreated = $production === null;

        if ($wasCreated) {
            Production::query()->create($attributes);
            $message = __('Production record created.');
        } else {
            $production->update($attributes);
            $message = __('Production record updated.');
        }

        $this->resetProductionForm();
        $this->resetPage();
        unset($this->productions);
        Flux::modal('production-form')->close();

        Flux::toast(variant: 'success', text: $message);
        $this->dispatch('egg-operations-changed', showGradingPrompt: $wasCreated && EggGrading::availableEggQuantity() > 0);
    }

    public function editProduction(int $productionId): void
    {
        $this->ensureAuthenticated();

        $production = Production::query()->findOrFail($productionId);

        $this->editingProductionId = $production->id;
        $this->productionDate = $production->production_date->toDateString();
        $this->totalEggs = $production->total_eggs;
        $this->damagedEggs = $production->damaged_eggs;
        $this->notes = $production->notes ?? '';
        $this->resetValidation();
        Flux::modal('production-form')->show();
    }

    public function cancelEditing(): void
    {
        $this->resetProductionForm();
        Flux::modal('production-form')->close();
    }

    public function confirmProductionDeletion(int $productionId): void
    {
        $this->ensureAuthenticated();

        Production::query()->findOrFail($productionId);
        $this->productionPendingDeletionId = $productionId;
        $this->resetValidation('deleteProduction');

        Flux::modal('delete-production')->show();
    }

    public function deleteProduction(): void
    {
        $this->ensureAuthenticated();

        $production = Production::query()->findOrFail($this->productionPendingDeletionId);

        $this->ensureEnoughEggsRemainForGrading($production, 0, 0, 'deleteProduction');
        $production->delete();

        $this->productionPendingDeletionId = null;
        $this->resetPage();
        unset($this->productions);
        Flux::modal('delete-production')->close();
        Flux::toast(variant: 'success', text: __('Production record deleted.'));
        $this->dispatch('egg-operations-changed');
    }

    /** @return array<string, mixed> */
    private function productionRules(): array
    {
        return [
            'productionDate' => [
                'required',
                'date',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $productionOnDate = Production::query()->whereDate('production_date', $value);

                    if ($this->editingProductionId !== null) {
                        $productionOnDate->where('id', '!=', $this->editingProductionId);
                    }

                    if ($productionOnDate->exists()) {
                        $fail(__('A production record already exists for this date.'));
                    }
                },
            ],
            'totalEggs' => ['required', 'integer', 'min:0'],
            'damagedEggs' => ['required', 'integer', 'min:0', 'lte:totalEggs'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function resetProductionForm(): void
    {
        $this->reset('editingProductionId', 'totalEggs', 'damagedEggs', 'notes');
        $this->productionDate = now()->toDateString();
        $this->resetValidation();
    }

    private function ensureEnoughEggsRemainForGrading(
        ?Production $production,
        int $totalEggs,
        int $damagedEggs,
        string $errorKey,
    ): void
    {
        if ($production === null) {
            return;
        }

        $currentGradableEggs = $production->total_eggs - $production->damaged_eggs;
        $newGradableEggs = $totalEggs - $damagedEggs;
        $projectedAvailableEggs = EggGrading::availableEggQuantity() - $currentGradableEggs + $newGradableEggs;

        if ($projectedAvailableEggs < 0) {
            throw ValidationException::withMessages([
                $errorKey => __('This change would leave fewer produced eggs than have already been graded.'),
            ]);
        }
    }

    private function ensureAuthenticated(): void
    {
        abort_unless(Auth::check(), 403);
    }
};
?>

<section class="{{ $embedded ? 'space-y-6' : 'page-shell' }}">
    <div class="page-header">
        <div>
            <flux:heading size="xl" :level="$embedded ? '2' : '1'">{{ __('Daily production') }}</flux:heading>
            <flux:subheading>{{ __('Record collected and damaged eggs before grading.') }}</flux:subheading>
        </div>

        <flux:modal.trigger name="production-form">
            <flux:button variant="primary" icon="plus">{{ __('Record production') }}</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:card class="data-panel space-y-4">
            <div class="flex justify-end">
                <flux:input class="w-full sm:max-w-xs" wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search production notes" aria-label="Search production notes" clearable />
            </div>
            @if ($this->productions->isEmpty())
                <div class="empty-state">
                    <flux:heading>{{ filled($search) ? __('No production records found') : __('No production records yet') }}</flux:heading>
                    <flux:subheading>{{ filled($search) ? __('Try a different note keyword.') : __('Record the first daily collection to begin tracking output.') }}</flux:subheading>
                    @unless (filled($search))
                    <flux:modal.trigger name="production-form">
                        <flux:button class="mt-4" size="sm">{{ __('Record production') }}</flux:button>
                    </flux:modal.trigger>
                    @endunless
                </div>
            @else
                <flux:table :paginate="$this->productions">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Collected') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Damaged') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('For grading') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->productions as $production)
                            <flux:table.row :key="$production->id">
                                <flux:table.cell variant="strong">
                                    <div class="flex flex-col">
                                        <span>{{ $production->production_date->format('d M Y') }}</span>
                                        @if ($production->notes)
                                            <span class="max-w-56 truncate text-xs font-normal text-zinc-500 dark:text-zinc-400" title="{{ $production->notes }}">
                                                {{ $production->notes }}
                                            </span>
                                        @endif
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell align="end">{{ number_format($production->total_eggs) }}</flux:table.cell>
                                <flux:table.cell align="end">{{ number_format($production->damaged_eggs) }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:badge color="green" size="sm">
                                        {{ number_format($production->total_eggs - $production->damaged_eggs) }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    <div class="flex justify-end gap-2">
                                        <flux:button size="sm" variant="ghost" wire:click="editProduction({{ $production->id }})">
                                            {{ __('Edit') }}
                                        </flux:button>
                                        <flux:button size="sm" variant="danger" wire:click="confirmProductionDeletion({{ $production->id }})">
                                            {{ __('Delete') }}
                                        </flux:button>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
    </flux:card>

    <flux:modal name="production-form" class="max-w-xl" @close="$wire.cancelEditing()">
        <form wire:submit="saveProduction" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editingProductionId === null ? __('Record production') : __('Edit production') }}</flux:heading>
                <flux:subheading>{{ __('Only one production record is allowed per day.') }}</flux:subheading>
            </div>

            <flux:input wire:model="productionDate" :label="__('Production date')" type="date" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model.live="totalEggs" :label="__('Total eggs collected')" type="number" min="0" required />
                <flux:input wire:model.live="damagedEggs" :label="__('Damaged eggs')" type="number" min="0" required />
            </div>

            <div class="rounded-xl bg-emerald-50 px-4 py-3 dark:bg-emerald-950/40">
                <flux:text class="text-sm">{{ __('Expected for grading') }}</flux:text>
                <flux:heading size="lg">{{ number_format($this->expectedGradableEggs) }} {{ __('eggs') }}</flux:heading>
            </div>

            <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" placeholder="Optional production notes" />

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="cancelEditing">{{ __('Cancel') }}</flux:button>
                <flux:button class="action-button" variant="primary" type="submit" data-test="save-production">
                    {{ $editingProductionId === null ? __('Record production') : __('Save changes') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-production" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete production record?') }}</flux:heading>
                <flux:subheading>{{ __('This removes the daily collection record permanently.') }}</flux:subheading>
            </div>

            @error('deleteProduction')
                <flux:callout variant="danger" icon="exclamation-circle" :heading="$message" />
            @enderror

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deleteProduction" data-test="confirm-delete-production">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
