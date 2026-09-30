<?php

use App\Models\EggGrade;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Egg grades')] class extends Component {
    #[Locked]
    public ?int $editingGradeId = null;

    #[Locked]
    public ?int $gradePendingDeletionId = null;

    public string $name = '';
    public string $weightRange = '';
    public int $sortOrder = 0;
    public bool $isActive = true;
    public string $pricePerEgg = '';
    public string $pricePerTray = '';

    /** @return Collection<int, EggGrade> */
    #[Computed]
    public function grades(): Collection
    {
        return EggGrade::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function saveGrade(): void
    {
        $this->ensureAuthenticated();

        $validated = $this->validate($this->gradeRules());

        EggGrade::query()->updateOrCreate(
            ['id' => $this->editingGradeId],
            [
                'name' => $validated['name'],
                'weight_range' => filled($validated['weightRange']) ? $validated['weightRange'] : null,
                'sort_order' => $validated['sortOrder'],
                'is_active' => $validated['isActive'],
                'price_per_egg' => filled($validated['pricePerEgg']) ? $validated['pricePerEgg'] : null,
                'price_per_tray' => filled($validated['pricePerTray']) ? $validated['pricePerTray'] : null,
            ],
        );

        $message = $this->editingGradeId === null
            ? __('Egg grade created.')
            : __('Egg grade updated.');

        $this->resetGradeForm();
        unset($this->grades);
        Flux::modal('grade-form')->close();

        Flux::toast(variant: 'success', text: $message);
    }

    public function editGrade(int $gradeId): void
    {
        $this->ensureAuthenticated();

        $grade = EggGrade::query()->findOrFail($gradeId);

        $this->editingGradeId = $grade->id;
        $this->name = $grade->name;
        $this->weightRange = $grade->weight_range ?? '';
        $this->sortOrder = $grade->sort_order;
        $this->isActive = $grade->is_active;
        $this->pricePerEgg = $grade->price_per_egg ?? '';
        $this->pricePerTray = $grade->price_per_tray ?? '';
        $this->resetValidation();
        Flux::modal('grade-form')->show();
    }

    public function cancelEditing(): void
    {
        $this->resetGradeForm();
        Flux::modal('grade-form')->close();
    }

    public function toggleGrade(int $gradeId): void
    {
        $this->ensureAuthenticated();

        $grade = EggGrade::query()->findOrFail($gradeId);
        $grade->update(['is_active' => ! $grade->is_active]);

        unset($this->grades);

        Flux::toast(
            variant: 'success',
            text: $grade->is_active ? __('Egg grade activated.') : __('Egg grade deactivated.'),
        );
    }

    public function confirmGradeDeletion(int $gradeId): void
    {
        $this->ensureAuthenticated();

        EggGrade::query()->findOrFail($gradeId);
        $this->gradePendingDeletionId = $gradeId;

        Flux::modal('delete-grade')->show();
    }

    public function deleteGrade(): void
    {
        $this->ensureAuthenticated();

        $grade = EggGrade::query()->findOrFail($this->gradePendingDeletionId);

        if ($grade->hasHistory()) {
            $grade->update(['is_active' => false]);
            $message = __('This grade has transaction history, so it was deactivated instead of deleted.');
            $variant = 'warning';
        } else {
            $grade->delete();
            $message = __('Egg grade deleted.');
            $variant = 'success';
        }

        $this->gradePendingDeletionId = null;
        unset($this->grades);
        Flux::modal('delete-grade')->close();
        Flux::toast(variant: $variant, text: $message);
    }

    /** @return array<string, mixed> */
    private function gradeRules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(EggGrade::class, 'name')->ignore($this->editingGradeId),
            ],
            'weightRange' => ['nullable', 'string', 'max:255'],
            'sortOrder' => ['required', 'integer', 'between:0,65535'],
            'isActive' => ['required', 'boolean'],
            'pricePerEgg' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
            'pricePerTray' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
        ];
    }

    private function resetGradeForm(): void
    {
        $this->reset('editingGradeId', 'name', 'weightRange', 'sortOrder', 'isActive', 'pricePerEgg', 'pricePerTray');
        $this->isActive = true;
        $this->resetValidation();
    }

    private function ensureAuthenticated(): void
    {
        abort_unless(Auth::check(), 403);
    }
};
?>

<section class="page-shell max-w-6xl">
    <div class="page-header">
        <div>
            <flux:heading size="xl" level="1">{{ __('Egg grades') }}</flux:heading>
            <flux:subheading>{{ __('Configure the grades used for grading, stock, and sales.') }}</flux:subheading>
        </div>
        <flux:modal.trigger name="grade-form">
            <flux:button variant="primary" icon="plus">{{ __('Add egg grade') }}</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:modal name="grade-form" class="max-w-xl" @close="$wire.cancelEditing()">
            <form wire:submit="saveGrade" class="space-y-5">
                <div>
                    <flux:heading size="lg">
                        {{ $editingGradeId === null ? __('Add egg grade') : __('Edit egg grade') }}
                    </flux:heading>
                    <flux:subheading>{{ __('Grade names remain linked to their transaction history.') }}</flux:subheading>
                </div>

                <flux:input wire:model="name" :label="__('Name')" placeholder="Grade A" required />
                <flux:input wire:model="weightRange" :label="__('Weight range')" placeholder="65–69.9 g" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="pricePerEgg" :label="__('Default price per egg (RM)')" type="number" min="0.01" step="0.01" />
                    <flux:input wire:model="pricePerTray" :label="__('Default price per tray (RM)')" type="number" min="0.01" step="0.01" />
                </div>
                <flux:text class="text-xs">{{ __('These prices prefill new sales and can be changed for an individual sale.') }}</flux:text>
                <flux:input wire:model="sortOrder" :label="__('Sort order')" type="number" min="0" max="65535" required />
                <flux:switch wire:model="isActive" :label="__('Active')" />

                <div class="flex flex-wrap justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="cancelEditing">{{ __('Cancel') }}</flux:button>

                    <flux:button class="action-button" variant="primary" type="submit" data-test="save-egg-grade">
                        {{ $editingGradeId === null ? __('Add grade') : __('Save changes') }}
                    </flux:button>
                </div>
            </form>
    </flux:modal>

    <flux:card class="data-panel">
            @if ($this->grades->isEmpty())
                <div class="empty-state">
                    <flux:heading>{{ __('No egg grades yet') }}</flux:heading>
                    <flux:subheading>{{ __('Add the first grade to begin grading and tracking stock.') }}</flux:subheading>
                    <flux:modal.trigger name="grade-form">
                        <flux:button class="mt-4" size="sm">{{ __('Add egg grade') }}</flux:button>
                    </flux:modal.trigger>
                </div>
            @else
                <div class="space-y-3 sm:hidden">
                    @foreach ($this->grades as $grade)
                        <article wire:key="mobile-grade-{{ $grade->id }}" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold">{{ $grade->name }}</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $grade->weight_range ?? __('No weight range') }}</p>
                                </div>
                                <flux:badge :color="$grade->is_active ? 'green' : null" size="sm">{{ $grade->is_active ? __('Active') : __('Inactive') }}</flux:badge>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                                <div><p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Per egg') }}</p><p class="font-medium">{{ $grade->price_per_egg === null ? '—' : 'RM '.$grade->price_per_egg }}</p></div>
                                <div><p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Per tray') }}</p><p class="font-medium">{{ $grade->price_per_tray === null ? '—' : 'RM '.$grade->price_per_tray }}</p></div>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                                <flux:button size="sm" variant="ghost" wire:click="editGrade({{ $grade->id }})">{{ __('Edit') }}</flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="toggleGrade({{ $grade->id }})">{{ $grade->is_active ? __('Deactivate') : __('Activate') }}</flux:button>
                                <flux:button size="sm" variant="danger" wire:click="confirmGradeDeletion({{ $grade->id }})">{{ __('Delete') }}</flux:button>
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="hidden sm:block">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Grade') }}</flux:table.column>
                        <flux:table.column>{{ __('Weight range') }}</flux:table.column>
                        <flux:table.column>{{ __('Default prices') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->grades as $grade)
                            <flux:table.row :key="$grade->id">
                                <flux:table.cell variant="strong">
                                    <div class="flex flex-col">
                                        <span>{{ $grade->name }}</span>
                                        <span class="text-xs font-normal text-zinc-500 dark:text-zinc-400">
                                            {{ __('Order: :order', ['order' => $grade->sort_order]) }}
                                        </span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>{{ $grade->weight_range ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex flex-col text-sm">
                                        <span>{{ __('Egg: :price', ['price' => $grade->price_per_egg === null ? '—' : 'RM '.$grade->price_per_egg]) }}</span>
                                        <span>{{ __('Tray: :price', ['price' => $grade->price_per_tray === null ? '—' : 'RM '.$grade->price_per_tray]) }}</span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$grade->is_active ? 'green' : null" size="sm">
                                        {{ $grade->is_active ? __('Active') : __('Inactive') }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    <div class="flex justify-end gap-2">
                                        <flux:button size="sm" variant="ghost" wire:click="editGrade({{ $grade->id }})">
                                            {{ __('Edit') }}
                                        </flux:button>
                                        <flux:button size="sm" variant="ghost" wire:click="toggleGrade({{ $grade->id }})">
                                            {{ $grade->is_active ? __('Deactivate') : __('Activate') }}
                                        </flux:button>
                                        <flux:button size="sm" variant="danger" wire:click="confirmGradeDeletion({{ $grade->id }})">
                                            {{ __('Delete') }}
                                        </flux:button>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
                </div>
            @endif
    </flux:card>

    <flux:modal name="delete-grade" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete egg grade?') }}</flux:heading>
                <flux:subheading>
                    {{ __('Unused grades are deleted. Grades with transaction history are kept and deactivated.') }}
                </flux:subheading>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deleteGrade" data-test="confirm-delete-grade">
                    {{ __('Continue') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
