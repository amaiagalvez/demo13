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
                    <flux:table.column class="resource-list-actions">
                        <span class="sr-only">{{ __('Actions') }}</span>
                    </flux:table.column>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Projects') }}</flux:table.column>
                    <flux:table.column>{{ __('Epics') }}</flux:table.column>
                    <flux:table.column>{{ __('Comments') }}</flux:table.column>
                    @if ($list['extraDateHeading'])
                        <flux:table.column>{{ $list['extraDateHeading'] }}</flux:table.column>
                    @endif
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($list['rows'] as $row)
                        <flux:table.row :key="$row['id']"
                            class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <flux:table.cell class="resource-list-actions">
                                <x-list.row-actions :actions="$row['actions']" prefix="customer"
                                    payload-key="customer" edit-handler="editCustomer" />
                            </flux:table.cell>
                            <flux:table.cell class="max-w-[16rem] truncate font-medium" :title="$row['name']"
                                :data-test="'customer-name-'.$row['id']">
                                {{ $row['name'] }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" icon="briefcase"
                                    :data-test="'customer-projects-count-'.$row['id']">
                                    {{ $row['projectsCount'] }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" icon="rectangle-stack"
                                    :data-test="'customer-epics-count-'.$row['id']">
                                    {{ $row['epicsCount'] }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" icon="chat-bubble-left"
                                    :data-test="'customer-comments-count-'.$row['id']">
                                    {{ $row['commentsCount'] }}
                                </flux:badge>
                            </flux:table.cell>
                            @if ($list['extraDateHeading'])
                                <flux:table.cell>
                                    <x-list.local-time :datetime="$row['extraDate']"
                                        format="datetime" />
                                </flux:table.cell>
                            @endif
                        </flux:table.row>
                    @empty
                        <x-list.empty-state :colspan="$list['create'] ? 5 : 6" :message="$list['emptyMessage']">
                            @if ($list['create'] && $list['search']['value'] === '')
                                <flux:modal.trigger name="customer-form">
                                    <flux:button size="sm" variant="primary" icon="plus"
                                        x-on:click="createCustomer()" data-test="customer-empty-create">
                                        {{ __('New customer') }}
                                    </flux:button>
                                </flux:modal.trigger>
                            @endif
                        </x-list.empty-state>
                    @endforelse
                </flux:table.rows>
            </x-list.table>
        </x-list.searchable-results>
        @endfragment

        @if ($list['create'])
            <flux:modal name="customer-form" aria-labelledby="customer-form-heading" variant="flyout" position="right"
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
