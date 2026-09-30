<?php

use App\Actions\StockAdjustment\DeleteStockAdjustment;
use App\Actions\StockAdjustment\SaveStockAdjustment;
use App\Models\EggGrade;
use App\Models\StockAdjustment;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Stock adjustments')] class extends Component {
    use WithPagination;

    public bool $embedded = false;

    #[Locked]
    public ?int $editingAdjustmentId = null;

    #[Locked]
    public ?int $adjustmentPendingDeletionId = null;

    public string $adjustmentDate = '';
    public ?int $eggGradeId = null;
    public string $type = 'remove';
    public int $quantity = 1;
    public string $reason = '';

    public function mount(bool $embedded = false): void
    {
        $this->embedded = $embedded;
        $this->adjustmentDate = now()->toDateString();
    }

    /** @return Collection<int, EggGrade> */
    #[Computed]
    public function selectableGrades(): Collection
    {
        return EggGrade::query()
            ->where(function (Builder $query): void {
                $query->where('is_active', true);

                if ($this->editingAdjustmentId !== null && $this->eggGradeId !== null) {
                    $query->orWhere('id', $this->eggGradeId);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @return LengthAwarePaginator<int, StockAdjustment> */
    #[Computed]
    public function adjustments(): LengthAwarePaginator
    {
        return StockAdjustment::query()
            ->with('eggGrade:id,name')
            ->orderByDesc('adjustment_date')
            ->orderByDesc('id')
            ->paginate(15);
    }

    #[Computed]
    public function availableStock(): int
    {
        if ($this->eggGradeId === null) {
            return 0;
        }

        $grade = EggGrade::query()->find($this->eggGradeId);

        if ($grade === null) {
            return 0;
        }

        $availableStock = $grade->stockQuantity();

        if ($this->editingAdjustmentId !== null) {
            $adjustment = StockAdjustment::query()->find($this->editingAdjustmentId);

            if ($adjustment?->egg_grade_id === $grade->id) {
                $availableStock -= $adjustment->type === 'add'
                    ? $adjustment->quantity
                    : -$adjustment->quantity;
            }
        }

        return $availableStock;
    }

    /** @return array<int, array{id: int, name: string, is_active: bool, quantity: int}> */
    #[Computed]
    public function stockLevels(): array
    {
        return EggGrade::query()
            ->withSum('gradings as graded_quantity', 'quantity')
            ->withSum('sales as sold_quantity', 'normalized_egg_quantity')
            ->withSum([
                'stockAdjustments as added_quantity' => fn (Builder $query): Builder => $query->where('type', 'add'),
            ], 'quantity')
            ->withSum([
                'stockAdjustments as removed_quantity' => fn (Builder $query): Builder => $query->where('type', 'remove'),
            ], 'quantity')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (EggGrade $grade): array => [
                'id' => $grade->id,
                'name' => $grade->name,
                'is_active' => $grade->is_active,
                'quantity' => (int) $grade->getAttribute('graded_quantity')
                    + (int) $grade->getAttribute('added_quantity')
                    - (int) $grade->getAttribute('sold_quantity')
                    - (int) $grade->getAttribute('removed_quantity'),
            ])
            ->all();
    }

    public function saveAdjustment(SaveStockAdjustment $saveStockAdjustment): void
    {
        $this->ensureAuthenticated();

        $validated = $this->validate($this->adjustmentRules());

        $saveStockAdjustment->handle($this->editingAdjustmentId, [
            'adjustment_date' => $validated['adjustmentDate'],
            'egg_grade_id' => (int) $validated['eggGradeId'],
            'type' => $validated['type'],
            'quantity' => (int) $validated['quantity'],
            'reason' => $validated['reason'],
        ]);

        $message = $this->editingAdjustmentId === null
            ? __('Stock adjustment created.')
            : __('Stock adjustment updated.');

        $this->resetAdjustmentForm();
        $this->resetPage();
        $this->clearComputedData();
        Flux::modal('adjustment-form')->close();

        Flux::toast(variant: 'success', text: $message);
        $this->dispatch('egg-operations-changed');
    }

    public function editAdjustment(int $adjustmentId): void
    {
        $this->ensureAuthenticated();

        $adjustment = StockAdjustment::query()->findOrFail($adjustmentId);

        $this->editingAdjustmentId = $adjustment->id;
        $this->adjustmentDate = $adjustment->adjustment_date->toDateString();
        $this->eggGradeId = $adjustment->egg_grade_id;
        $this->type = $adjustment->type;
        $this->quantity = $adjustment->quantity;
        $this->reason = $adjustment->reason;
        $this->resetValidation();
        $this->clearComputedData();
        Flux::modal('adjustment-form')->show();
    }

    public function cancelEditing(): void
    {
        $this->resetAdjustmentForm();
        $this->clearComputedData();
        Flux::modal('adjustment-form')->close();
    }

    public function confirmAdjustmentDeletion(int $adjustmentId): void
    {
        $this->ensureAuthenticated();

        StockAdjustment::query()->findOrFail($adjustmentId);
        $this->adjustmentPendingDeletionId = $adjustmentId;
        $this->resetValidation('deleteAdjustment');

        Flux::modal('delete-stock-adjustment')->show();
    }

    public function deleteAdjustment(DeleteStockAdjustment $deleteStockAdjustment): void
    {
        $this->ensureAuthenticated();

        $deleteStockAdjustment->handle($this->adjustmentPendingDeletionId);

        $this->adjustmentPendingDeletionId = null;
        $this->resetPage();
        $this->clearComputedData();
        Flux::modal('delete-stock-adjustment')->close();
        Flux::toast(variant: 'success', text: __('Stock adjustment deleted.'));
        $this->dispatch('egg-operations-changed');
    }

    /** @return array<string, mixed> */
    private function adjustmentRules(): array
    {
        return [
            'adjustmentDate' => ['required', 'date'],
            'eggGradeId' => ['required', 'integer', Rule::exists(EggGrade::class, 'id')],
            'type' => ['required', Rule::in(['add', 'remove'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    private function resetAdjustmentForm(): void
    {
        $this->reset('editingAdjustmentId', 'eggGradeId', 'reason');
        $this->adjustmentDate = now()->toDateString();
        $this->type = 'remove';
        $this->quantity = 1;
        $this->resetValidation();
    }

    private function clearComputedData(): void
    {
        unset($this->selectableGrades, $this->adjustments, $this->availableStock, $this->stockLevels);
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
            <flux:heading size="xl" :level="$embedded ? '2' : '1'">{{ __('Stock adjustments') }}</flux:heading>
            <flux:subheading>{{ __('Record breakage, spoilage, counting corrections, and manual stock additions.') }}</flux:subheading>
        </div>
        <flux:modal.trigger name="adjustment-form">
            <flux:button variant="primary" icon="plus">{{ __('Add adjustment') }}</flux:button>
        </flux:modal.trigger>
    </div>

    @if ($this->stockLevels !== [])
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($this->stockLevels as $stockLevel)
                <flux:card wire:key="adjustment-stock-{{ $stockLevel['id'] }}" class="metric-card space-y-1">
                    <div class="flex items-center justify-between gap-3">
                        <flux:text class="truncate font-medium">{{ $stockLevel['name'] }}</flux:text>
                        @unless ($stockLevel['is_active'])
                            <flux:badge size="sm">{{ __('Inactive') }}</flux:badge>
                        @endunless
                    </div>
                    <flux:heading size="lg">{{ number_format($stockLevel['quantity']) }}</flux:heading>
                    <flux:text class="text-xs">{{ __('eggs in stock') }}</flux:text>
                </flux:card>
            @endforeach
        </div>
    @endif

    <flux:modal name="adjustment-form" class="max-w-xl" @close="$wire.cancelEditing()">
            <form wire:submit="saveAdjustment" class="space-y-5">
                <div>
                    <flux:heading size="lg">
                        {{ $editingAdjustmentId === null ? __('Add adjustment') : __('Edit adjustment') }}
                    </flux:heading>
                    <flux:subheading>{{ __('Removals cannot reduce a grade below zero stock.') }}</flux:subheading>
                </div>

                @if ($this->selectableGrades->isEmpty())
                    <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('No active egg grades')">
                        <flux:callout.text>{{ __('Create or activate an egg grade before adjusting stock.') }}</flux:callout.text>
                    </flux:callout>
                @endif

                <flux:input wire:model="adjustmentDate" :label="__('Adjustment date')" type="date" required />

                <flux:select wire:model.live="eggGradeId" :label="__('Egg grade')" :placeholder="__('Select a grade')" required>
                    @foreach ($this->selectableGrades as $grade)
                        <flux:select.option :value="$grade->id" wire:key="adjustment-grade-option-{{ $grade->id }}">
                            {{ $grade->name }}{{ $grade->is_active ? '' : ' ('.__('Inactive').')' }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="type" :label="__('Adjustment type')" required>
                    <flux:select.option value="add">{{ __('Add stock') }}</flux:select.option>
                    <flux:select.option value="remove">{{ __('Remove stock') }}</flux:select.option>
                </flux:select>

                <flux:input wire:model="quantity" :label="__('Quantity')" type="number" min="1" max="4294967295" required />
                <flux:input wire:model="reason" :label="__('Reason')" placeholder="Broken eggs" maxlength="255" required />

                @if ($eggGradeId !== null)
                    <div class="rounded-lg bg-zinc-100 px-3 py-3 dark:bg-zinc-800">
                        <flux:text class="text-xs">{{ __('Stock before this adjustment') }}</flux:text>
                        <flux:heading size="sm">{{ number_format(max(0, $this->availableStock)) }} {{ __('eggs') }}</flux:heading>
                    </div>
                @endif

                <div class="flex flex-wrap justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="cancelEditing">{{ __('Cancel') }}</flux:button>

                    <flux:button class="action-button" variant="primary" type="submit" :disabled="$this->selectableGrades->isEmpty()" data-test="save-stock-adjustment">
                        {{ $editingAdjustmentId === null ? __('Add adjustment') : __('Save changes') }}
                    </flux:button>
                </div>
            </form>
    </flux:modal>

    <flux:card class="data-panel">
            @if ($this->adjustments->isEmpty())
                <div class="empty-state">
                    <flux:heading>{{ __('No stock adjustments yet') }}</flux:heading>
                    <flux:subheading>{{ __('Use adjustments only when physical stock differs from recorded stock.') }}</flux:subheading>
                    <flux:modal.trigger name="adjustment-form">
                        <flux:button class="mt-4" size="sm">{{ __('Add adjustment') }}</flux:button>
                    </flux:modal.trigger>
                </div>
            @else
                <flux:table :paginate="$this->adjustments">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                        <flux:table.column>{{ __('Grade') }}</flux:table.column>
                        <flux:table.column>{{ __('Reason') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Quantity') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->adjustments as $adjustment)
                            <flux:table.row :key="$adjustment->id">
                                <flux:table.cell>{{ $adjustment->adjustment_date->format('d M Y') }}</flux:table.cell>
                                <flux:table.cell>{{ $adjustment->eggGrade->name }}</flux:table.cell>
                                <flux:table.cell variant="strong">{{ $adjustment->reason }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:badge :color="$adjustment->type === 'add' ? 'green' : 'red'" size="sm">
                                        {{ $adjustment->type === 'add' ? '+' : '−' }}{{ number_format($adjustment->quantity) }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    <div class="flex justify-end gap-2">
                                        <flux:button size="sm" variant="ghost" wire:click="editAdjustment({{ $adjustment->id }})">
                                            {{ __('Edit') }}
                                        </flux:button>
                                        <flux:button size="sm" variant="danger" wire:click="confirmAdjustmentDeletion({{ $adjustment->id }})">
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

    <flux:modal name="delete-stock-adjustment" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete stock adjustment?') }}</flux:heading>
                <flux:subheading>{{ __('The stock total will be recalculated without this adjustment.') }}</flux:subheading>
            </div>

            @error('deleteAdjustment')
                <flux:callout variant="danger" icon="exclamation-circle" :heading="$message" />
            @enderror

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deleteAdjustment" data-test="confirm-delete-stock-adjustment">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
