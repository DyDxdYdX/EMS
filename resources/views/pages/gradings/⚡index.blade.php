<?php

use App\Actions\EggGrading\DeleteEggGrading;
use App\Actions\EggGrading\SaveEggGrading;
use App\Models\EggGrade;
use App\Models\EggGrading;
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

new #[Title('Egg grading')] class extends Component {
    use WithPagination;

    public bool $embedded = false;

    #[Locked]
    public ?int $editingGradingId = null;

    #[Locked]
    public ?int $gradingPendingDeletionId = null;

    public string $gradingDate = '';
    public ?int $eggGradeId = null;
    public int $quantity = 0;
    public string $notes = '';

    public function mount(bool $embedded = false): void
    {
        $this->embedded = $embedded;
        $this->gradingDate = now()->toDateString();
        $this->eggGradeId = $this->selectableGrades->first()?->id;
    }

    /** @return Collection<int, EggGrade> */
    #[Computed]
    public function selectableGrades(): Collection
    {
        return EggGrade::query()
            ->where(function (Builder $query): void {
                $query->where('is_active', true);

                if ($this->editingGradingId !== null && $this->eggGradeId !== null) {
                    $query->orWhere('id', $this->eggGradeId);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @return LengthAwarePaginator<int, EggGrading> */
    #[Computed]
    public function gradings(): LengthAwarePaginator
    {
        return EggGrading::query()
            ->with('eggGrade:id,name')
            ->orderByDesc('grading_date')
            ->orderByDesc('id')
            ->paginate(15);
    }

    #[Computed]
    public function availableToGrade(): int
    {
        $editingGrading = $this->editingGradingId === null
            ? null
            : EggGrading::query()->find($this->editingGradingId);

        return EggGrading::availableEggQuantity($editingGrading);
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

    public function saveGrading(SaveEggGrading $saveEggGrading): void
    {
        $this->ensureAuthenticated();

        $validated = $this->validate($this->gradingRules());

        $saveEggGrading->handle($this->editingGradingId, [
            'grading_date' => $validated['gradingDate'],
            'egg_grade_id' => (int) $validated['eggGradeId'],
            'quantity' => (int) $validated['quantity'],
            'notes' => filled($validated['notes']) ? $validated['notes'] : null,
        ]);

        $message = $this->editingGradingId === null
            ? __('Grading record created.')
            : __('Grading record updated.');

        $this->resetGradingForm();
        $this->resetPage();
        $this->clearComputedData();
        Flux::modal('grading-form')->close();

        Flux::toast(variant: 'success', text: $message);
        $this->dispatch('egg-operations-changed');
    }

    public function editGrading(int $gradingId): void
    {
        $this->ensureAuthenticated();

        $grading = EggGrading::query()->findOrFail($gradingId);

        $this->editingGradingId = $grading->id;
        $this->gradingDate = $grading->grading_date->toDateString();
        $this->eggGradeId = $grading->egg_grade_id;
        $this->quantity = $grading->quantity;
        $this->notes = $grading->notes ?? '';
        $this->resetValidation();
        $this->clearComputedData();
        Flux::modal('grading-form')->show();
    }

    public function cancelEditing(): void
    {
        $this->resetGradingForm();
        $this->clearComputedData();
        Flux::modal('grading-form')->close();
    }

    public function confirmGradingDeletion(int $gradingId): void
    {
        $this->ensureAuthenticated();

        EggGrading::query()->findOrFail($gradingId);
        $this->gradingPendingDeletionId = $gradingId;
        $this->resetValidation('deleteGrading');

        Flux::modal('delete-grading')->show();
    }

    public function deleteGrading(DeleteEggGrading $deleteEggGrading): void
    {
        $this->ensureAuthenticated();

        $deleteEggGrading->handle($this->gradingPendingDeletionId);

        $this->gradingPendingDeletionId = null;
        $this->resetPage();
        $this->clearComputedData();
        Flux::modal('delete-grading')->close();
        Flux::toast(variant: 'success', text: __('Grading record deleted.'));
        $this->dispatch('egg-operations-changed');
    }

    /** @return array<string, mixed> */
    private function gradingRules(): array
    {
        return [
            'gradingDate' => ['required', 'date'],
            'eggGradeId' => [
                'required',
                'integer',
                Rule::exists(EggGrade::class, 'id'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $duplicateGrading = EggGrading::query()
                        ->whereDate('grading_date', $this->gradingDate)
                        ->where('egg_grade_id', $value)
                        ->when($this->editingGradingId !== null, fn ($query) => $query->where('id', '!=', $this->editingGradingId))
                        ->exists();

                    if ($duplicateGrading) {
                        $fail(__('A grading record for this grade on this date already exists.'));
                    }
                },
            ],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function resetGradingForm(): void
    {
        $this->reset('editingGradingId', 'quantity', 'notes');
        $this->gradingDate = now()->toDateString();
        $this->clearComputedData();
        $this->eggGradeId = $this->selectableGrades->first()?->id;
        $this->resetValidation();
    }

    private function clearComputedData(): void
    {
        unset($this->selectableGrades, $this->gradings, $this->availableToGrade, $this->stockLevels);
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
            <flux:heading size="xl" :level="$embedded ? '2' : '1'">{{ __('Egg grading') }}</flux:heading>
            <flux:subheading>{{ __('Turn collected eggs into grade-level sellable stock.') }}</flux:subheading>
        </div>

        <div class="flex items-end gap-3">
            @unless ($embedded)
                <div class="rounded-xl bg-amber-50 px-4 py-3 text-start sm:text-end dark:bg-amber-950/40">
                    <flux:text class="text-sm">{{ __('Ungraded eggs available') }}</flux:text>
                    <flux:heading size="lg" data-test="available-to-grade">{{ number_format(max(0, $this->availableToGrade)) }}</flux:heading>
                </div>
            @endunless
            <flux:modal.trigger name="grading-form">
                <flux:button variant="primary" icon="plus">{{ __('Record grading') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    @if ($this->stockLevels !== [])
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($this->stockLevels as $stockLevel)
                <flux:card wire:key="stock-{{ $stockLevel['id'] }}" class="metric-card space-y-1">
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

    <flux:modal name="grading-form" class="max-w-xl" @close="$wire.cancelEditing()">
            <form wire:submit="saveGrading" class="space-y-5">
                <div>
                    <flux:heading size="lg">
                        {{ $editingGradingId === null ? __('Add grading') : __('Edit grading') }}
                    </flux:heading>
                    <flux:subheading>{{ __('Graded quantities are added directly to stock.') }}</flux:subheading>
                </div>

                @if ($this->selectableGrades->isEmpty())
                    <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('No active egg grades')">
                        <flux:callout.text>
                            {{ __('Create or activate an egg grade before recording grading.') }}
                        </flux:callout.text>
                        <x-slot name="actions">
                            <flux:button size="sm" :href="route('egg-grades.index')" wire:navigate>
                                {{ __('Manage grades') }}
                            </flux:button>
                        </x-slot>
                    </flux:callout>
                @endif

                <flux:input wire:model="gradingDate" :label="__('Grading date')" type="date" required />

                <flux:select wire:model="eggGradeId" :label="__('Egg grade')" required>
                    @foreach ($this->selectableGrades as $grade)
                        <flux:select.option :value="$grade->id" wire:key="grade-option-{{ $grade->id }}">
                            {{ $grade->name }}{{ $grade->is_active ? '' : ' ('.__('Inactive').')' }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="quantity" :label="__('Quantity graded')" type="number" min="1" required />
                <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" placeholder="Optional grading notes" />

                <div class="flex flex-wrap justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="cancelEditing">{{ __('Cancel') }}</flux:button>

                    <flux:button class="action-button" variant="primary" type="submit" :disabled="$this->selectableGrades->isEmpty()" data-test="save-grading">
                        {{ $editingGradingId === null ? __('Add grading') : __('Save changes') }}
                    </flux:button>
                </div>
            </form>
    </flux:modal>

    <flux:card class="data-panel">
            @if ($this->gradings->isEmpty())
                <div class="empty-state">
                    <flux:heading>{{ __('No grading records yet') }}</flux:heading>
                    <flux:subheading>{{ __('Record production first, then allocate the available eggs into grades.') }}</flux:subheading>
                    <flux:modal.trigger name="grading-form">
                        <flux:button class="mt-4" size="sm">{{ __('Record grading') }}</flux:button>
                    </flux:modal.trigger>
                </div>
            @else
                <flux:table :paginate="$this->gradings">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                        <flux:table.column>{{ __('Grade') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Quantity') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->gradings as $grading)
                            <flux:table.row :key="$grading->id">
                                <flux:table.cell variant="strong">
                                    <div class="flex flex-col">
                                        <span>{{ $grading->grading_date->format('d M Y') }}</span>
                                        @if ($grading->notes)
                                            <span class="max-w-56 truncate text-xs font-normal text-zinc-500 dark:text-zinc-400" title="{{ $grading->notes }}">
                                                {{ $grading->notes }}
                                            </span>
                                        @endif
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>{{ $grading->eggGrade->name }}</flux:table.cell>
                                <flux:table.cell align="end">{{ number_format($grading->quantity) }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <div class="flex justify-end gap-2">
                                        <flux:button size="sm" variant="ghost" wire:click="editGrading({{ $grading->id }})">
                                            {{ __('Edit') }}
                                        </flux:button>
                                        <flux:button size="sm" variant="danger" wire:click="confirmGradingDeletion({{ $grading->id }})">
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

    <flux:modal name="delete-grading" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete grading record?') }}</flux:heading>
                <flux:subheading>{{ __('The graded eggs will be removed from stock if they have not already been used.') }}</flux:subheading>
            </div>

            @error('deleteGrading')
                <flux:callout variant="danger" icon="exclamation-circle" :heading="$message" />
            @enderror

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deleteGrading" data-test="confirm-delete-grading">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
