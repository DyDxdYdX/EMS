<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
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

new #[Title('Expenses')] class extends Component {
    use WithPagination;

    #[Locked]
    public ?int $editingExpenseId = null;

    #[Locked]
    public ?int $expensePendingDeletionId = null;

    public string $expenseDate = '';
    public string $title = '';
    public ?int $expenseCategoryId = null;
    public string $amount = '0.00';
    public string $description = '';

    public function mount(): void
    {
        $this->expenseDate = now()->toDateString();
    }

    /** @return Collection<int, ExpenseCategory> */
    #[Computed]
    public function selectableCategories(): Collection
    {
        return ExpenseCategory::query()
            ->where(function (Builder $query): void {
                $query->where('is_active', true);

                if ($this->editingExpenseId !== null && $this->expenseCategoryId !== null) {
                    $query->orWhere('id', $this->expenseCategoryId);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @return LengthAwarePaginator<int, Expense> */
    #[Computed]
    public function expenses(): LengthAwarePaginator
    {
        return Expense::query()
            ->with('expenseCategory:id,name')
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(15);
    }

    #[Computed]
    public function totalExpenses(): string
    {
        return number_format((float) Expense::query()->sum('amount'), 2, '.', ',');
    }

    public function saveExpense(): void
    {
        $this->ensureAuthenticated();

        $validated = $this->validate($this->expenseRules());
        $category = ExpenseCategory::query()->findOrFail($validated['expenseCategoryId']);
        $existingExpense = $this->editingExpenseId === null
            ? null
            : Expense::query()->findOrFail($this->editingExpenseId);

        if (! $category->is_active && $existingExpense?->expense_category_id !== $category->id) {
            $this->addError('expenseCategoryId', __('The selected expense category is inactive.'));

            return;
        }

        Expense::query()->updateOrCreate(
            ['id' => $this->editingExpenseId],
            [
                'expense_date' => $validated['expenseDate'],
                'title' => $validated['title'],
                'expense_category_id' => $category->id,
                'amount' => (string) $validated['amount'],
                'description' => filled($validated['description']) ? $validated['description'] : null,
            ],
        );

        $message = $this->editingExpenseId === null
            ? __('Expense created.')
            : __('Expense updated.');

        $this->resetExpenseForm();
        $this->resetPage();
        $this->clearComputedData();

        Flux::toast(variant: 'success', text: $message);
    }

    public function editExpense(int $expenseId): void
    {
        $this->ensureAuthenticated();

        $expense = Expense::query()->findOrFail($expenseId);

        $this->editingExpenseId = $expense->id;
        $this->expenseDate = $expense->expense_date->toDateString();
        $this->title = $expense->title;
        $this->expenseCategoryId = $expense->expense_category_id;
        $this->amount = $expense->amount;
        $this->description = $expense->description ?? '';
        $this->resetValidation();
        $this->clearComputedData();
    }

    public function cancelEditing(): void
    {
        $this->resetExpenseForm();
        $this->clearComputedData();
    }

    public function confirmExpenseDeletion(int $expenseId): void
    {
        $this->ensureAuthenticated();

        Expense::query()->findOrFail($expenseId);
        $this->expensePendingDeletionId = $expenseId;

        Flux::modal('delete-expense')->show();
    }

    public function deleteExpense(): void
    {
        $this->ensureAuthenticated();

        Expense::query()->findOrFail($this->expensePendingDeletionId)->delete();

        $this->expensePendingDeletionId = null;
        $this->resetPage();
        $this->clearComputedData();
        Flux::modal('delete-expense')->close();
        Flux::toast(variant: 'success', text: __('Expense deleted.'));
    }

    /** @return array<string, mixed> */
    private function expenseRules(): array
    {
        return [
            'expenseDate' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'expenseCategoryId' => ['required', 'integer', Rule::exists(ExpenseCategory::class, 'id')],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function resetExpenseForm(): void
    {
        $this->reset('editingExpenseId', 'title', 'expenseCategoryId', 'description');
        $this->expenseDate = now()->toDateString();
        $this->amount = '0.00';
        $this->resetValidation();
    }

    private function clearComputedData(): void
    {
        unset($this->selectableCategories, $this->expenses, $this->totalExpenses);
    }

    private function ensureAuthenticated(): void
    {
        abort_unless(Auth::check(), 403);
    }
};
?>

<section class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Expenses') }}</flux:heading>
            <flux:subheading>{{ __('Record farm costs for accurate profit and loss tracking.') }}</flux:subheading>
        </div>
        <div class="sm:text-right">
            <flux:text class="text-sm">{{ __('All-time expenses') }}</flux:text>
            <flux:heading size="lg">{{ $this->totalExpenses }}</flux:heading>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,24rem)_minmax(0,1fr)]">
        <flux:card>
            <form wire:submit="saveExpense" class="space-y-5">
                <div>
                    <flux:heading size="lg">
                        {{ $editingExpenseId === null ? __('Add expense') : __('Edit expense') }}
                    </flux:heading>
                    <flux:subheading>
                        <a class="underline" href="{{ route('expense-categories.index') }}" wire:navigate>{{ __('Manage expense categories') }}</a>
                    </flux:subheading>
                </div>

                @if ($this->selectableCategories->isEmpty())
                    <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('No active expense categories')">
                        <flux:callout.text>{{ __('Create a category before recording an expense.') }}</flux:callout.text>
                    </flux:callout>
                @endif

                <flux:input wire:model="expenseDate" :label="__('Expense date')" type="date" required />
                <flux:input wire:model="title" :label="__('Title')" placeholder="Layer feed" required />

                <flux:select wire:model="expenseCategoryId" :label="__('Category')" :placeholder="__('Select a category')" required>
                    @foreach ($this->selectableCategories as $category)
                        <flux:select.option :value="$category->id" wire:key="expense-category-option-{{ $category->id }}">
                            {{ $category->name }}{{ $category->is_active ? '' : ' ('.__('Inactive').')' }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="amount" :label="__('Amount')" type="number" min="0.01" step="0.01" required />
                <flux:textarea wire:model="description" :label="__('Description')" rows="3" placeholder="Optional expense details" />

                <div class="flex flex-wrap justify-end gap-2">
                    @if ($editingExpenseId !== null)
                        <flux:button type="button" variant="ghost" wire:click="cancelEditing">
                            {{ __('Cancel') }}
                        </flux:button>
                    @endif

                    <flux:button variant="primary" type="submit" :disabled="$this->selectableCategories->isEmpty()" data-test="save-expense">
                        {{ $editingExpenseId === null ? __('Add expense') : __('Save changes') }}
                    </flux:button>
                </div>
            </form>
        </flux:card>

        <flux:card class="min-w-0">
            @if ($this->expenses->isEmpty())
                <div class="py-10 text-center">
                    <flux:heading>{{ __('No expenses yet') }}</flux:heading>
                    <flux:subheading>{{ __('Record the first farm expense using the form.') }}</flux:subheading>
                </div>
            @else
                <flux:table :paginate="$this->expenses">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                        <flux:table.column>{{ __('Expense') }}</flux:table.column>
                        <flux:table.column>{{ __('Category') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->expenses as $expense)
                            <flux:table.row :key="$expense->id">
                                <flux:table.cell>{{ $expense->expense_date->format('d M Y') }}</flux:table.cell>
                                <flux:table.cell variant="strong">
                                    <div class="flex flex-col">
                                        <span>{{ $expense->title }}</span>
                                        @if ($expense->description)
                                            <span class="max-w-64 truncate text-xs font-normal text-zinc-500 dark:text-zinc-400" title="{{ $expense->description }}">
                                                {{ $expense->description }}
                                            </span>
                                        @endif
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>{{ $expense->expenseCategory->name }}</flux:table.cell>
                                <flux:table.cell align="end">{{ number_format((float) $expense->amount, 2) }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <div class="flex justify-end gap-2">
                                        <flux:button size="sm" variant="ghost" wire:click="editExpense({{ $expense->id }})">
                                            {{ __('Edit') }}
                                        </flux:button>
                                        <flux:button size="sm" variant="danger" wire:click="confirmExpenseDeletion({{ $expense->id }})">
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
    </div>

    <flux:modal name="delete-expense" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete expense?') }}</flux:heading>
                <flux:subheading>{{ __('This expense will be permanently removed from profit and loss calculations.') }}</flux:subheading>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deleteExpense" data-test="confirm-delete-expense">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
