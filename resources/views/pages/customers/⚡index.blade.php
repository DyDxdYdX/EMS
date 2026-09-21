<?php

use App\Models\Customer;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Customers')] class extends Component {
    use WithPagination;

    #[Locked]
    public ?int $editingCustomerId = null;

    #[Locked]
    public ?int $customerPendingDeletionId = null;

    public string $name = '';
    public string $phone = '';
    public string $notes = '';

    /** @return LengthAwarePaginator<int, Customer> */
    #[Computed]
    public function customers(): LengthAwarePaginator
    {
        return Customer::query()
            ->withCount('sales')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);
    }

    public function saveCustomer(): void
    {
        $this->ensureAuthenticated();

        $validated = $this->validate($this->customerRules());
        $attributes = [
            'name' => $validated['name'],
            'phone' => filled($validated['phone']) ? $validated['phone'] : null,
            'notes' => filled($validated['notes']) ? $validated['notes'] : null,
        ];

        if ($this->editingCustomerId === null) {
            Customer::query()->create($attributes);
            $message = __('Customer created.');
        } else {
            Customer::query()->findOrFail($this->editingCustomerId)->update($attributes);
            $message = __('Customer updated.');
        }

        $this->resetCustomerForm();
        $this->resetPage();
        unset($this->customers);

        Flux::toast(variant: 'success', text: $message);
    }

    public function editCustomer(int $customerId): void
    {
        $this->ensureAuthenticated();

        $customer = Customer::query()->findOrFail($customerId);

        $this->editingCustomerId = $customer->id;
        $this->name = $customer->name;
        $this->phone = $customer->phone ?? '';
        $this->notes = $customer->notes ?? '';
        $this->resetValidation();
    }

    public function cancelEditing(): void
    {
        $this->resetCustomerForm();
    }

    public function confirmCustomerDeletion(int $customerId): void
    {
        $this->ensureAuthenticated();

        Customer::query()->findOrFail($customerId);
        $this->customerPendingDeletionId = $customerId;

        Flux::modal('delete-customer')->show();
    }

    public function deleteCustomer(): void
    {
        $this->ensureAuthenticated();

        Customer::query()->findOrFail($this->customerPendingDeletionId)->delete();

        $this->customerPendingDeletionId = null;
        $this->resetPage();
        unset($this->customers);
        Flux::modal('delete-customer')->close();
        Flux::toast(variant: 'success', text: __('Customer deleted.'));
    }

    /** @return array<string, mixed> */
    private function customerRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function resetCustomerForm(): void
    {
        $this->reset('editingCustomerId', 'name', 'phone', 'notes');
        $this->resetValidation();
    }

    private function ensureAuthenticated(): void
    {
        abort_unless(Auth::check(), 403);
    }
};
?>

<section class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div>
        <flux:heading size="xl" level="1">{{ __('Customers') }}</flux:heading>
        <flux:subheading>{{ __('Maintain optional customer details for sales records.') }}</flux:subheading>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
        <flux:card>
            <form wire:submit="saveCustomer" class="space-y-5">
                <div>
                    <flux:heading size="lg">
                        {{ $editingCustomerId === null ? __('Add customer') : __('Edit customer') }}
                    </flux:heading>
                    <flux:subheading>{{ __('A sale can also be recorded without selecting a customer.') }}</flux:subheading>
                </div>

                <flux:input wire:model="name" :label="__('Name')" required />
                <flux:input wire:model="phone" :label="__('Phone')" type="tel" />
                <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" placeholder="Optional customer notes" />

                <div class="flex flex-wrap justify-end gap-2">
                    @if ($editingCustomerId !== null)
                        <flux:button type="button" variant="ghost" wire:click="cancelEditing">
                            {{ __('Cancel') }}
                        </flux:button>
                    @endif

                    <flux:button variant="primary" type="submit" data-test="save-customer">
                        {{ $editingCustomerId === null ? __('Add customer') : __('Save changes') }}
                    </flux:button>
                </div>
            </form>
        </flux:card>

        <flux:card class="min-w-0">
            @if ($this->customers->isEmpty())
                <div class="py-10 text-center">
                    <flux:heading>{{ __('No customers yet') }}</flux:heading>
                    <flux:subheading>{{ __('Add a customer now or continue using walk-in sales.') }}</flux:subheading>
                </div>
            @else
                <flux:table :paginate="$this->customers">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Customer') }}</flux:table.column>
                        <flux:table.column>{{ __('Phone') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Sales') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->customers as $customer)
                            <flux:table.row :key="$customer->id">
                                <flux:table.cell variant="strong">
                                    <div class="flex flex-col">
                                        <span>{{ $customer->name }}</span>
                                        @if ($customer->notes)
                                            <span class="max-w-56 truncate text-xs font-normal text-zinc-500 dark:text-zinc-400" title="{{ $customer->notes }}">
                                                {{ $customer->notes }}
                                            </span>
                                        @endif
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>{{ $customer->phone ?? '—' }}</flux:table.cell>
                                <flux:table.cell align="end">{{ number_format($customer->sales_count) }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <div class="flex justify-end gap-2">
                                        <flux:button size="sm" variant="ghost" wire:click="editCustomer({{ $customer->id }})">
                                            {{ __('Edit') }}
                                        </flux:button>
                                        <flux:button size="sm" variant="danger" wire:click="confirmCustomerDeletion({{ $customer->id }})">
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

    <flux:modal name="delete-customer" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete customer?') }}</flux:heading>
                <flux:subheading>{{ __('Past sales are preserved and changed to walk-in sales.') }}</flux:subheading>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deleteCustomer" data-test="confirm-delete-customer">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
