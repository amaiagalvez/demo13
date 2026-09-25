@php
    $modalWithErrors = match (true) {
        old('_customer_form') === 'create' => 'customer-create',
        str_starts_with(old('_customer_form', ''), 'edit-') => 'customer-edit',
        default => null,
    };
@endphp

<x-layouts::app :title="__('Customers')">
    <div x-data="{
        customer: @js([
    'id' => old('_customer_id'),
    'name' => str_starts_with(old('_customer_form', ''), 'edit-') ? old('name', '') : '',
]),
        updateUrl: @js(route('customers.update', '__CUSTOMER__')),
        destroyUrl: @js(route('customers.destroy', '__CUSTOMER__')),
        selectCustomer(customer) {
            this.customer = customer;
        },
        customerUrl(template) {
            return template.replace('__CUSTOMER__', this.customer.id);
        },
    }"
        @if ($errors->any() && $modalWithErrors) x-init="$nextTick(() => $dispatch('modal-show', { name: @js($modalWithErrors) }))" @endif>
        <div class="flex flex-col gap-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <flux:heading size="xl">{{ __('Customers') }}</flux:heading>
                    <flux:subheading>{{ __('Manage your customers.') }}</flux:subheading>
                </div>
                <div class="flex items-center gap-2">
                    <flux:button :href="route('customers.trash.index')" variant="ghost" icon="trash"
                        wire:navigate data-test="customer-trash-link">
                        {{ __('Trash') }}
                    </flux:button>
                    <flux:modal.trigger name="customer-create">
                        <flux:button variant="primary" icon="plus"
                            data-test="customer-create-button">
                            {{ __('New customer') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            </div>

            @if (session('status'))
                <flux:callout icon="check-circle" variant="success">{{ session('status') }}
                </flux:callout>
            @endif

            <div
                class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Created at') }}</flux:table.column>
                        <flux:table.column class="text-end">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($customers as $customer)
                            <flux:table.row :key="$customer->id">
                                <flux:table.cell class="font-medium">{{ $customer->name }}
                                </flux:table.cell>
                                <flux:table.cell>{{ $customer->created_at->format('Y-m-d') }}
                                </flux:table.cell>
                                <flux:table.cell class="text-end">
                                    <div class="flex justify-end gap-2">
                                        <flux:modal.trigger name="customer-edit">
                                            <flux:button size="sm" variant="ghost"
                                                icon="pencil-square"
                                                x-on:click="selectCustomer(@js($customer->only(['id', 'name'])))"
                                                data-test="customer-edit-{{ $customer->id }}">
                                                {{ __('Edit') }}
                                            </flux:button>
                                        </flux:modal.trigger>
                                        <flux:modal.trigger name="customer-delete">
                                            <flux:button size="sm" variant="ghost"
                                                icon="trash" class="text-red-600"
                                                x-on:click="selectCustomer(@js($customer->only(['id', 'name'])))"
                                                data-test="customer-delete-{{ $customer->id }}">
                                                {{ __('Delete') }}
                                            </flux:button>
                                        </flux:modal.trigger>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="3"
                                    class="py-8 text-center text-zinc-500">
                                    {{ __('No customers yet.') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>

            {{ $customers->links() }}
        </div>

        <flux:modal name="customer-create" variant="flyout" position="right"
            class="customer-drawer max-w-none">
            @include('customers.form', [
                'customer' => null,
                'formTitle' => __('New customer'),
                'formSubtitle' => __('Add a customer to your records.'),
                'formAction' => route('customers.store'),
                'formMethod' => 'POST',
                'formContext' => 'create',
                'submitLabel' => __('Save customer'),
            ])
        </flux:modal>

        <flux:modal name="customer-edit" variant="flyout" position="right"
            class="customer-drawer max-w-none">
            @include('customers.form', [
                'formTitle' => __('Edit customer'),
                'formSubtitle' => __('Update the customer details.'),
                'formMethod' => 'PUT',
                'submitLabel' => __('Update customer'),
                'usesSelectedCustomer' => true,
            ])
        </flux:modal>

        <flux:modal name="customer-delete" class="max-w-md">
            <div class="flex flex-col gap-6">
                <div>
                    <flux:heading size="lg">{{ __('Delete customer?') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('You can restore it from the trash.') }}
                    </flux:text>
                </div>

                <div class="flex justify-end gap-3">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <form method="POST" x-bind:action="customerUrl(destroyUrl)">
                        @csrf
                        @method('DELETE')
                        <flux:button variant="danger" type="submit"
                            data-test="customer-delete-confirm">
                            {{ __('Delete') }}
                        </flux:button>
                    </form>
                </div>
            </div>
        </flux:modal>
    </div>
</x-layouts::app>
