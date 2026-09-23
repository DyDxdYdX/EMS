<?php

use App\Models\ExpenseCategory;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Expense categories')] class extends Component {
    #[Locked]
    public ?int $editingCategoryId = null;

    #[Locked]
    public ?int $categoryPendingDeletionId = null;

    public string $name = '';
    public int $sortOrder = 0;
    public bool $isActive = true;

    /** @return Collection<int, ExpenseCategory> */
    #[Computed]
    public function categories(): Collection
    {
        return ExpenseCategory::query()
            ->withCount('expenses')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function saveCategory(): void
    {
        $this->ensureAuthenticated();

        $validated = $this->validate($this->categoryRules());

        ExpenseCategory::query()->updateOrCreate(
            ['id' => $this->editingCategoryId],
            [
                'name' => $validated['name'],
                'sort_order' => $validated['sortOrder'],
                'is_active' => $validated['isActive'],
            ],
        );

        $message = $this->editingCategoryId === null
            ? __('Expense category created.')
            : __('Expense category updated.');

        $this->resetCategoryForm();
        unset($this->categories);
        Flux::modal('category-form')->close();

        Flux::toast(variant: 'success', text: $message);
    }

    public function editCategory(int $categoryId): void
    {
        $this->ensureAuthenticated();

        $category = ExpenseCategory::query()->findOrFail($categoryId);

        $this->editingCategoryId = $category->id;
        $this->name = $category->name;
        $this->sortOrder = $category->sort_order;
        $this->isActive = $category->is_active;
        $this->resetValidation();
        Flux::modal('category-form')->show();
    }

    public function cancelEditing(): void
    {
        $this->resetCategoryForm();
        Flux::modal('category-form')->close();
    }

    public function toggleCategory(int $categoryId): void
    {
        $this->ensureAuthenticated();

        $category = ExpenseCategory::query()->findOrFail($categoryId);
        $category->update(['is_active' => ! $category->is_active]);

        unset($this->categories);

        Flux::toast(
            variant: 'success',
            text: $category->is_active ? __('Expense category activated.') : __('Expense category deactivated.'),
        );
    }

    public function confirmCategoryDeletion(int $categoryId): void
    {
        $this->ensureAuthenticated();

        ExpenseCategory::query()->findOrFail($categoryId);
        $this->categoryPendingDeletionId = $categoryId;

        Flux::modal('delete-expense-category')->show();
    }

    public function deleteCategory(): void
    {
        $this->ensureAuthenticated();

        $category = ExpenseCategory::query()->findOrFail($this->categoryPendingDeletionId);

        if ($category->hasHistory()) {
            $category->update(['is_active' => false]);
            $message = __('This category has expense history, so it was deactivated instead of deleted.');
            $variant = 'warning';
        } else {
            $category->delete();
            $message = __('Expense category deleted.');
            $variant = 'success';
        }

        $this->categoryPendingDeletionId = null;
        unset($this->categories);
        Flux::modal('delete-expense-category')->close();
        Flux::toast(variant: $variant, text: $message);
    }

    /** @return array<string, mixed> */
    private function categoryRules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(ExpenseCategory::class, 'name')->ignore($this->editingCategoryId),
            ],
            'sortOrder' => ['required', 'integer', 'between:0,65535'],
            'isActive' => ['required', 'boolean'],
        ];
    }

    private function resetCategoryForm(): void
    {
        $this->reset('editingCategoryId', 'name', 'sortOrder', 'isActive');
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
            <flux:heading size="xl" level="1">{{ __('Expense categories') }}</flux:heading>
            <flux:subheading>{{ __('Organize farm costs for clear profit and loss reporting.') }}</flux:subheading>
        </div>
        <flux:modal.trigger name="category-form">
            <flux:button variant="primary" icon="plus">{{ __('Add category') }}</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:modal name="category-form" class="max-w-xl" @close="$wire.cancelEditing()">
            <form wire:submit="saveCategory" class="space-y-5">
                <div>
                    <flux:heading size="lg">
                        {{ $editingCategoryId === null ? __('Add expense category') : __('Edit expense category') }}
                    </flux:heading>
                    <flux:subheading>{{ __('Categories with expense history are retained for accurate reports.') }}</flux:subheading>
                </div>

                <flux:input wire:model="name" :label="__('Name')" placeholder="Feed" required />
                <flux:input wire:model="sortOrder" :label="__('Sort order')" type="number" min="0" max="65535" required />
                <flux:switch wire:model="isActive" :label="__('Active')" />

                <div class="flex flex-wrap justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="cancelEditing">{{ __('Cancel') }}</flux:button>

                    <flux:button class="action-button" variant="primary" type="submit" data-test="save-expense-category">
                        {{ $editingCategoryId === null ? __('Add category') : __('Save changes') }}
                    </flux:button>
                </div>
            </form>
    </flux:modal>

    <flux:card class="data-panel">
            @if ($this->categories->isEmpty())
                <div class="empty-state">
                    <flux:heading>{{ __('No expense categories yet') }}</flux:heading>
                    <flux:subheading>{{ __('Add the first category to organize farm costs.') }}</flux:subheading>
                    <flux:modal.trigger name="category-form">
                        <flux:button class="mt-4" size="sm">{{ __('Add category') }}</flux:button>
                    </flux:modal.trigger>
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Category') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Expenses') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->categories as $category)
                            <flux:table.row :key="$category->id">
                                <flux:table.cell variant="strong">
                                    <div class="flex flex-col">
                                        <span>{{ $category->name }}</span>
                                        <span class="text-xs font-normal text-zinc-500 dark:text-zinc-400">
                                            {{ __('Order: :order', ['order' => $category->sort_order]) }}
                                        </span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell align="end">{{ number_format($category->expenses_count) }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$category->is_active ? 'green' : null" size="sm">
                                        {{ $category->is_active ? __('Active') : __('Inactive') }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    <div class="flex justify-end gap-2">
                                        <flux:button size="sm" variant="ghost" wire:click="editCategory({{ $category->id }})">
                                            {{ __('Edit') }}
                                        </flux:button>
                                        <flux:button size="sm" variant="ghost" wire:click="toggleCategory({{ $category->id }})">
                                            {{ $category->is_active ? __('Deactivate') : __('Activate') }}
                                        </flux:button>
                                        <flux:button size="sm" variant="danger" wire:click="confirmCategoryDeletion({{ $category->id }})">
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

    <flux:modal name="delete-expense-category" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete expense category?') }}</flux:heading>
                <flux:subheading>
                    {{ __('Unused categories are deleted. Categories with expense history are kept and deactivated.') }}
                </flux:subheading>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deleteCategory" data-test="confirm-delete-expense-category">
                    {{ __('Continue') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
