<?php

use App\Actions\Sales\SaveSale;
use App\Models\Customer;
use App\Models\EggGrade;
use App\Models\FarmSetting;
use App\Models\Sale;
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

new #[Title('Sales')] class extends Component {
    use WithPagination;

    #[Locked]
    public ?int $editingSaleId = null;

    #[Locked]
    public ?int $salePendingDeletionId = null;

    public string $saleDate = '';
    public string $customerType = 'walk_in';
    public ?int $customerId = null;
    public ?int $eggGradeId = null;
    public string $unit = 'tray';
    public int $quantity = 1;
    public string $unitPrice = '0.00';
    public string $notes = '';
    public string $search = '';
    public string $customerSearch = '';

    public function mount(): void
    {
        $this->saleDate = now()->toDateString();
        $this->eggGradeId = $this->selectableGrades->first()?->id;
    }

    /** @return Collection<int, Customer> */
    #[Computed]
    public function customers(): Collection
    {
        $search = trim($this->customerSearch);

        $customers = Customer::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'name', 'phone']);

        if ($this->customerId !== null && ! $customers->contains('id', $this->customerId)) {
            $selectedCustomer = Customer::query()->find($this->customerId, ['id', 'name', 'phone']);

            if ($selectedCustomer !== null) {
                $customers->prepend($selectedCustomer);
            }
        }

        return $customers;
    }

    public function updatedCustomerSearch(): void
    {
        $this->customerId = null;
        unset($this->customers);
        $this->resetValidation('customerId');
    }

    public function selectCustomer(int $customerId): void
    {
        $this->ensureAuthenticated();

        $customer = Customer::query()->findOrFail($customerId);

        $this->customerId = $customer->id;
        $this->customerSearch = $customer->name;
        unset($this->customers);
        $this->resetValidation('customerId');
    }

    public function updatedCustomerType(): void
    {
        if ($this->customerType === 'walk_in') {
            $this->customerId = null;
            $this->customerSearch = '';
            unset($this->customers);
            $this->resetValidation('customerId');
        }
    }

    /** @return Collection<int, EggGrade> */
    #[Computed]
    public function selectableGrades(): Collection
    {
        return EggGrade::query()
            ->where(function (Builder $query): void {
                $query->where('is_active', true);

                if ($this->editingSaleId !== null && $this->eggGradeId !== null) {
                    $query->orWhere('id', $this->eggGradeId);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @return LengthAwarePaginator<int, Sale> */
    #[Computed]
    public function sales(): LengthAwarePaginator
    {
        return Sale::query()
            ->with(['customer:id,name', 'eggGrade:id,name'])
            ->when(filled($this->search), function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->whereHas('customer', fn (Builder $query): Builder => $query->where('name', 'like', '%'.$this->search.'%'))
                        ->orWhereHas('eggGrade', fn (Builder $query): Builder => $query->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->paginate(15);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        unset($this->sales);
    }

    #[Computed]
    public function eggsPerTray(): int
    {
        return (int) (FarmSetting::query()->value('eggs_per_tray') ?? 30);
    }

    #[Computed]
    public function normalizedEggQuantity(): int
    {
        return Sale::normalizedEggQuantity($this->unit, max(0, $this->quantity), $this->eggsPerTray);
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

        if ($this->editingSaleId !== null) {
            $sale = Sale::query()->find($this->editingSaleId);

            if ($sale?->egg_grade_id === $grade->id) {
                $availableStock += $sale->normalized_egg_quantity;
            }
        }

        return $availableStock;
    }

    #[Computed]
    public function totalPreview(): string
    {
        if (! is_numeric($this->unitPrice) || $this->quantity < 1) {
            return '0.00';
        }

        return number_format($this->quantity * (float) $this->unitPrice, 2, '.', ',');
    }

    public function saveSale(SaveSale $saveSale): void
    {
        $this->ensureAuthenticated();

        $validated = $this->validate($this->saleRules());

        $saveSale->handle($this->editingSaleId, [
            'sale_date' => $validated['saleDate'],
            'customer_id' => $validated['customerType'] === 'existing' ? (int) $validated['customerId'] : null,
            'egg_grade_id' => (int) $validated['eggGradeId'],
            'unit' => $validated['unit'],
            'quantity' => (int) $validated['quantity'],
            'unit_price' => (string) $validated['unitPrice'],
            'notes' => filled($validated['notes']) ? $validated['notes'] : null,
        ]);

        $message = $this->editingSaleId === null
            ? __('Sale created.')
            : __('Sale updated.');

        $this->resetSaleForm();
        $this->resetPage();
        $this->clearComputedData();
        Flux::modal('sale-form')->close();

        Flux::toast(variant: 'success', text: $message);
    }

    public function editSale(int $saleId): void
    {
        $this->ensureAuthenticated();

        $sale = Sale::query()->with('customer:id,name')->findOrFail($saleId);

        $this->editingSaleId = $sale->id;
        $this->saleDate = $sale->sale_date->toDateString();
        $this->customerType = $sale->customer_id === null ? 'walk_in' : 'existing';
        $this->customerId = $sale->customer_id;
        $this->customerSearch = $sale->customer?->name ?? '';
        $this->eggGradeId = $sale->egg_grade_id;
        $this->unit = $sale->unit;
        $this->quantity = $sale->quantity;
        $this->unitPrice = $sale->unit_price;
        $this->notes = $sale->notes ?? '';
        $this->resetValidation();
        $this->clearComputedData();
        Flux::modal('sale-form')->show();
    }

    public function cancelEditing(): void
    {
        $this->resetSaleForm();
        $this->clearComputedData();
        Flux::modal('sale-form')->close();
    }

    public function confirmSaleDeletion(int $saleId): void
    {
        $this->ensureAuthenticated();

        Sale::query()->findOrFail($saleId);
        $this->salePendingDeletionId = $saleId;

        Flux::modal('delete-sale')->show();
    }

    public function deleteSale(): void
    {
        $this->ensureAuthenticated();

        Sale::query()->findOrFail($this->salePendingDeletionId)->delete();

        $this->salePendingDeletionId = null;
        $this->resetPage();
        $this->clearComputedData();
        Flux::modal('delete-sale')->close();
        Flux::toast(variant: 'success', text: __('Sale deleted and stock restored.'));
    }

    /** @return array<string, mixed> */
    private function saleRules(): array
    {
        return [
            'saleDate' => ['required', 'date'],
            'customerType' => ['required', Rule::in(['walk_in', 'existing'])],
            'customerId' => [Rule::requiredIf($this->customerType === 'existing'), 'nullable', 'integer', Rule::exists(Customer::class, 'id')],
            'eggGradeId' => ['required', 'integer', Rule::exists(EggGrade::class, 'id')],
            'unit' => ['required', Rule::in(['egg', 'tray'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'unitPrice' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function resetSaleForm(): void
    {
        $this->reset('editingSaleId', 'notes');
        $this->customerType = 'walk_in';
        $this->customerId = null;
        $this->customerSearch = '';
        $this->saleDate = now()->toDateString();
        $this->unit = 'tray';
        $this->quantity = 1;
        $this->unitPrice = '0.00';
        $this->clearComputedData();
        $this->eggGradeId = $this->selectableGrades->first()?->id;
        $this->resetValidation();
    }

    private function clearComputedData(): void
    {
        unset(
            $this->customers,
            $this->selectableGrades,
            $this->sales,
            $this->eggsPerTray,
            $this->normalizedEggQuantity,
            $this->availableStock,
            $this->totalPreview,
        );
    }

    private function ensureAuthenticated(): void
    {
        abort_unless(Auth::check(), 403);
    }
};
?>

<section class="page-shell">
    <div class="page-header">
        <div>
            <flux:heading size="xl" level="1">{{ __('Sales') }}</flux:heading>
            <flux:subheading>{{ __('Record egg and tray sales while keeping stock accurate.') }}</flux:subheading>
        </div>
        <flux:modal.trigger name="sale-form">
            <flux:button variant="primary" icon="plus">{{ __('Record sale') }}</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:modal name="sale-form" class="max-w-2xl" @close="$wire.cancelEditing()">
            <form wire:submit="saveSale" class="space-y-5">
                <div>
                    <flux:heading size="lg">
                        {{ $editingSaleId === null ? __('Add sale') : __('Edit sale') }}
                    </flux:heading>
                    <flux:subheading>{{ __('Stock is checked again when the sale is saved.') }}</flux:subheading>
                </div>

                @if ($this->selectableGrades->isEmpty())
                    <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('No active egg grades')">
                        <flux:callout.text>{{ __('Create a grade and add graded stock before recording a sale.') }}</flux:callout.text>
                    </flux:callout>
                @endif

                <flux:input wire:model="saleDate" :label="__('Sale date')" type="date" required />

                <flux:radio.group wire:model.live="customerType" variant="segmented" :label="__('Customer type')" class="w-full *:flex-1">
                    <flux:radio value="walk_in" :label="__('Walk-in')" />
                    <flux:radio value="existing" :label="__('Existing customer')" />
                </flux:radio.group>

                @if ($customerType === 'existing')
                    <div
                        class="relative"
                        x-data="{
                            open: false,
                            active: -1,
                            options() { return [...this.$refs.results.querySelectorAll('[data-customer-option]')]; },
                            move(step) {
                                this.open = true;
                                const count = this.options().length;
                                this.active = count ? (this.active + step + count) % count : -1;
                                this.options()[this.active]?.scrollIntoView({ block: 'nearest' });
                            },
                            chooseActive() { if (this.open && this.active >= 0) this.options()[this.active]?.click(); }
                        }"
                        x-on:click.outside="open = false"
                        x-on:focusout="if (! $el.contains($event.relatedTarget)) { open = false; active = -1 }"
                    >
                        <flux:input
                            wire:model.live.debounce.200ms="customerSearch"
                            :label="__('Customer')"
                            icon="magnifying-glass"
                            icon:trailing="chevron-down"
                            :placeholder="__('Search or select customer')"
                            maxlength="255"
                            role="combobox"
                            aria-autocomplete="list"
                            aria-controls="customer-options"
                            x-bind:aria-expanded="open.toString()"
                            x-bind:aria-activedescendant="active >= 0 ? options()[active]?.id : null"
                            x-on:focus="open = true; active = -1; $event.target.select()"
                            x-on:input="open = true; active = -1"
                            x-on:keydown.arrow-down.prevent="move(1)"
                            x-on:keydown.arrow-up.prevent="move(-1)"
                            x-on:keydown.enter.prevent="chooseActive()"
                            x-on:keydown.escape="open = false; active = -1"
                        />
                        <div
                            id="customer-options"
                            x-ref="results"
                            x-cloak
                            x-show="open"
                            role="listbox"
                            class="absolute z-30 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-zinc-200 bg-white p-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-800"
                        >
                            @forelse ($this->customers as $customer)
                                <button
                                    type="button"
                                    id="customer-result-{{ $customer->id }}"
                                    data-customer-option
                                    role="option"
                                    aria-selected="{{ $customerId === $customer->id ? 'true' : 'false' }}"
                                    wire:key="customer-option-{{ $customer->id }}"
                                    wire:click="selectCustomer({{ $customer->id }})"
                                    x-on:mouseenter="active = {{ $loop->index }}"
                                    x-on:click="open = false; active = -1"
                                    x-bind:class="active === {{ $loop->index }} ? 'bg-emerald-50 dark:bg-emerald-950/40' : ''"
                                    class="flex w-full flex-col rounded-md px-3 py-2 text-start text-sm text-zinc-800 hover:bg-zinc-100 focus:bg-zinc-100 focus:outline-none dark:text-zinc-100 dark:hover:bg-zinc-700 dark:focus:bg-zinc-700"
                                >
                                    <span class="font-medium">{{ $customer->name }}</span>
                                    @if ($customer->phone)
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $customer->phone }}</span>
                                    @endif
                                </button>
                            @empty
                                <div class="px-3 py-3 text-sm text-zinc-500 dark:text-zinc-400" role="status">{{ __('No matching customers') }}</div>
                            @endforelse
                        </div>
                        <flux:error name="customerId" />
                    </div>
                @else
                    <flux:text class="text-sm">{{ __('No customer details needed for a walk-in sale.') }}</flux:text>
                @endif

                <flux:select wire:model.live="eggGradeId" :label="__('Egg grade')" required>
                    @foreach ($this->selectableGrades as $grade)
                        <flux:select.option :value="$grade->id" wire:key="sale-grade-option-{{ $grade->id }}">
                            {{ $grade->name }}{{ $grade->is_active ? '' : ' ('.__('Inactive').')' }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model.live="unit" :label="__('Unit')" required>
                        <flux:select.option value="tray">{{ __('Tray') }}</flux:select.option>
                        <flux:select.option value="egg">{{ __('Egg') }}</flux:select.option>
                    </flux:select>
                    <flux:input wire:model.live="quantity" :label="__('Quantity')" type="number" min="1" required />
                </div>

                @if ($unit === 'tray')
                    <flux:text class="text-sm">
                        {{ __('Each tray contains :count eggs.', ['count' => number_format($this->eggsPerTray)]) }}
                    </flux:text>
                @endif

                <flux:input wire:model.live="unitPrice" :label="__('Price per unit')" type="number" min="0.01" step="0.01" required />
                <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" placeholder="Optional sale notes" />

                <div class="grid grid-cols-3 gap-2 rounded-lg bg-zinc-100 px-3 py-3 dark:bg-zinc-800">
                    <div>
                        <flux:text class="text-xs">{{ __('Available') }}</flux:text>
                        <flux:heading size="sm">{{ number_format(max(0, $this->availableStock)) }}</flux:heading>
                    </div>
                    <div>
                        <flux:text class="text-xs">{{ __('Eggs sold') }}</flux:text>
                        <flux:heading size="sm">{{ number_format($this->normalizedEggQuantity) }}</flux:heading>
                    </div>
                    <div>
                        <flux:text class="text-xs">{{ __('Total') }}</flux:text>
                        <flux:heading size="sm">{{ __('RM :amount', ['amount' => $this->totalPreview]) }}</flux:heading>
                    </div>
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="cancelEditing">{{ __('Cancel') }}</flux:button>

                    <flux:button class="action-button" variant="primary" type="submit" :disabled="$this->selectableGrades->isEmpty()" data-test="save-sale">
                        {{ $editingSaleId === null ? __('Add sale') : __('Save changes') }}
                    </flux:button>
                </div>
            </form>
    </flux:modal>

    <flux:card class="data-panel space-y-4">
            <div class="flex justify-end">
                <flux:input class="w-full sm:max-w-xs" wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search customer or grade" aria-label="Search sales" clearable />
            </div>
            @if ($this->sales->isEmpty())
                <div class="empty-state">
                    <flux:heading>{{ filled($search) ? __('No sales found') : __('No sales yet') }}</flux:heading>
                    <flux:subheading>{{ filled($search) ? __('Try a different customer or egg grade.') : __('Grade eggs into stock before recording the first sale.') }}</flux:subheading>
                    @unless (filled($search))
                    <flux:modal.trigger name="sale-form">
                        <flux:button class="mt-4" size="sm">{{ __('Record sale') }}</flux:button>
                    </flux:modal.trigger>
                    @endunless
                </div>
            @else
                <flux:table :paginate="$this->sales">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                        <flux:table.column>{{ __('Customer') }}</flux:table.column>
                        <flux:table.column>{{ __('Grade') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Quantity') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->sales as $sale)
                            <flux:table.row :key="$sale->id">
                                <flux:table.cell variant="strong">
                                    <div class="flex flex-col">
                                        <span>{{ $sale->sale_date->format('d M Y') }}</span>
                                        @if ($sale->notes)
                                            <span class="max-w-48 truncate text-xs font-normal text-zinc-500 dark:text-zinc-400" title="{{ $sale->notes }}">
                                                {{ $sale->notes }}
                                            </span>
                                        @endif
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>{{ $sale->customer?->name ?? __('Walk-in') }}</flux:table.cell>
                                <flux:table.cell>{{ $sale->eggGrade->name }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <div class="flex flex-col items-end">
                                        <span>{{ number_format($sale->quantity) }} {{ $sale->unit === 'tray' ? __('trays') : __('eggs') }}</span>
                                        @if ($sale->unit === 'tray')
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ number_format($sale->normalized_egg_quantity) }} {{ __('eggs') }}
                                            </span>
                                        @endif
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    <div class="flex flex-col items-end">
                                        <span>{{ __('RM :amount', ['amount' => number_format((float) $sale->total_amount, 2)]) }}</span>
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ __('RM :amount', ['amount' => number_format((float) $sale->unit_price, 2)]) }} / {{ $sale->unit }}
                                        </span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    <div class="flex justify-end gap-2">
                                        <flux:button size="sm" variant="ghost" wire:click="editSale({{ $sale->id }})">
                                            {{ __('Edit') }}
                                        </flux:button>
                                        <flux:button size="sm" variant="danger" wire:click="confirmSaleDeletion({{ $sale->id }})">
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

    <flux:modal name="delete-sale" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete sale?') }}</flux:heading>
                <flux:subheading>{{ __('The sale will be removed and its eggs returned to stock.') }}</flux:subheading>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deleteSale" data-test="confirm-delete-sale">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
