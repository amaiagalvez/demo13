@php
    $editingCustomer = $list['create'] && str_starts_with(old('_customer_form', ''), 'edit-');
    $deletedCustomerConflict = session('deleted_customer_conflict');
    $initialForm = $list['create']
        ? [
            'id' => $editingCustomer ? old('_customer_id') : null,
            'name' => old('name', ''),
            'context' => $editingCustomer ? old('_customer_form') : 'create',
            'method' => $editingCustomer ? 'PUT' : 'POST',
            'action' => $editingCustomer
                ? route('customers.update', old('_customer_id'))
                : route('customers.store'),
            'title' => $editingCustomer ? __('Edit customer') : __('New customer'),
            'subtitle' => $editingCustomer
                ? __('Update the customer details.')
                : __('Add a customer to your records.'),
            'submitLabel' => $editingCustomer ? __('Update customer') : __('Save customer'),
        ]
        : null;
@endphp

<x-layouts::app :title="$list['resource']">
    <div x-data="{
        form: @js($initialForm),
        confirmation: {
            action: '',
            method: 'DELETE',
            title: '',
            text: '',
            label: '',
            danger: false,
        },
        storeUrl: @js(route('customers.store')),
        updateUrl: @js(route('customers.update', '__CUSTOMER__')),
        createCustomer() {
            this.form = {
                id: null,
                name: '',
                context: 'create',
                method: 'POST',
                action: this.storeUrl,
                title: @js(__('New customer')),
                subtitle: @js(__('Add a customer to your records.')),
                submitLabel: @js(__('Save customer')),
            };
        },
        editCustomer(customer) {
            this.form = {
                ...customer,
                context: `edit-${customer.id}`,
                method: 'PUT',
                action: this.updateUrl.replace('__CUSTOMER__', customer.id),
                title: @js(__('Edit customer')),
                subtitle: @js(__('Update the customer details.')),
                submitLabel: @js(__('Update customer')),
            };
        },
        confirmAction(action) {
            this.confirmation = {
                action: action.action,
                method: action.method,
                title: action.confirmTitle,
                text: action.confirmText,
                label: action.confirmLabel,
                danger: action.danger,
            };
        },
    }"
        @if ($errors->any()) x-init="$nextTick(() => $dispatch('modal-show', { name: 'customer-form' }))"
        @elseif ($deletedCustomerConflict)
            x-init="$nextTick(() => $dispatch('modal-show', { name: 'customer-name-conflict' }))" @endif
        class="flex flex-col gap-4">
        <x-list.header :list="$list" prefix="customer" :create-label="__('New customer')"
            create-click="createCustomer()" />

        <x-list.flash prefix="customer" />

        @fragment('list-results')
        <x-list.searchable-results :search="$list['search']">
            <x-list.search :search="$list['search']" prefix="customer" />

            <x-list.table prefix="customer" :paginator="$customers">
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ $list['dateHeading'] }}</flux:table.column>
                    <flux:table.column class="text-end">
                        <span class="sr-only">{{ __('Actions') }}</span>
                    </flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($list['rows'] as $row)
                        <flux:table.row :key="$row['id']"
                            class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <flux:table.cell class="font-medium">{{ $row['name'] }}
                            </flux:table.cell>
                            <flux:table.cell>{{ $row['date'] }}</flux:table.cell>
                            <flux:table.cell class="text-end">
                                <x-list.row-actions :actions="$row['actions']" prefix="customer"
                                    payload-key="customer" edit-handler="editCustomer" />
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="py-8 text-center text-zinc-500">
                                <div class="flex flex-col items-center gap-3 px-4 py-4">
                                    <flux:icon.magnifying-glass class="size-8 text-zinc-400 dark:text-zinc-500"
                                        aria-hidden="true" />
                                    <span>{{ $list['emptyMessage'] }}</span>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </x-list.table>
        </x-list.searchable-results>
        @endfragment

        @if ($list['create'])
            <flux:modal name="customer-form" variant="flyout" position="right"
                x-on:close="window.clearForm($el.querySelector('form')); window.resetTrackedForms($el)"
                x-on:cancel.prevent="window.requestTrackedModalClose($el)"
                class="form-drawer max-w-none">
                @include('customers.form')
            </flux:modal>

            @if ($deletedCustomerConflict)
                <x-name-conflict-modal
                    name="customer-name-conflict"
                    :title="__('Customer name already in trash')"
                    :message="__('A deleted customer already uses the name :name.', ['name' => $deletedCustomerConflict['name']])"
                    :create-action="route('customers.store')"
                    :restore-action="route('customers.trash.restore', $deletedCustomerConflict['id'])"
                    :create-fields="['name' => old('name', $deletedCustomerConflict['name'])]"
                    :create-label="__('Create a new customer')"
                    :restore-label="__('Restore the deleted customer instead')"
                    create-test="customer-conflict-create-new"
                    restore-test="customer-conflict-restore" />
            @endif
        @endif

        <x-list.confirm-modal prefix="customer" />
    </div>
</x-layouts::app>
