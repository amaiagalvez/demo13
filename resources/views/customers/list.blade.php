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
            x-init="$nextTick(() => $dispatch('modal-show', { name: 'customer-name-conflict' }))"
        @elseif ($list['create'] && request()->boolean('create'))
            x-init="createCustomer(); $nextTick(() => $dispatch('modal-show', { name: 'customer-form' }))" @endif
        class="flex flex-col gap-y-2 sm:gap-y-3">
        <x-list.page-header :list="$list" prefix="customer">
            <x-slot:actions>
                @if ($list['create'])
                    <x-list.create-action prefix="customer" :label="__('New customer')"
                        click="createCustomer()" />
                @endif
            </x-slot:actions>
        </x-list.page-header>

        <x-list.flash prefix="customer" />

        @fragment('list-results')
            <x-list.searchable-results :search="$list['search']">
                <x-list.search :search="$list['search']" prefix="customer" />

                <x-list.table prefix="customer" :paginator="$customers">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Projects') }}</flux:table.column>
                        <flux:table.column>{{ __('Epics') }}</flux:table.column>
                        <flux:table.column>{{ __('Comments') }}</flux:table.column>
                        @if ($list['extraDateHeading'])
                            <flux:table.column>{{ $list['extraDateHeading'] }}</flux:table.column>
                        @endif
                        <flux:table.column align="end" sticky class="text-end">
                            <span class="sr-only">{{ __('Actions') }}</span>
                        </flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($list['rows'] as $row)
                            <flux:table.row :key="$row['id']"
                                class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <flux:table.cell class="max-w-[16rem]">
                                    <button type="button"
                                        class="block max-w-full truncate rounded text-start font-medium text-zinc-900 focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2 dark:text-zinc-100 dark:focus-visible:ring-brand-400"
                                        title="{{ $row['name'] }}"
                                        data-test="customer-name-{{ $row['id'] }}"
                                        x-on:click="editCustomer(JSON.parse($el.dataset.payload)); $dispatch('modal-show', { name: 'customer-form' })"
                                        data-payload="{{ json_encode($row['editPayload']) }}">{{ $row['name'] }}</button>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($row['projectsUrl'])
                                        <flux:button :href="$row['projectsUrl']" wire:navigate
                                            size="xs" variant="ghost" icon="briefcase"
                                            :aria-label="__('View :count projects', ['count' => $row[
                                                'projectsCount']])"
                                            data-test="customer-projects-count-{{ $row['id'] }}"
                                            class="hover:bg-zinc-100 hover:text-brand-700 dark:hover:bg-zinc-800 dark:hover:text-brand-400">
                                            {{ $row['projectsCount'] }}
                                        </flux:button>
                                    @else
                                        <span
                                            data-test="customer-projects-count-{{ $row['id'] }}"></span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($row['epicsCount'] > 0)
                                        <flux:badge size="sm" icon="flag"
                                            data-test="customer-epics-count-{{ $row['id'] }}"
                                            class="hover:text-brand-700 dark:hover:text-brand-400">
                                            {{ $row['epicsCount'] }}
                                        </flux:badge>
                                    @else
                                        <span
                                            data-test="customer-epics-count-{{ $row['id'] }}"></span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($row['commentsCount'] > 0)
                                        <flux:badge size="sm" icon="chat-bubble-left"
                                            data-test="customer-comments-count-{{ $row['id'] }}"
                                            class="hover:text-brand-700 dark:hover:text-brand-400">
                                            {{ $row['commentsCount'] }}
                                        </flux:badge>
                                    @else
                                        <span
                                            data-test="customer-comments-count-{{ $row['id'] }}"></span>
                                    @endif
                                </flux:table.cell>
                                @if ($list['extraDateHeading'])
                                    <flux:table.cell>
                                        <x-list.local-time :datetime="$row['extraDate']" format="datetime" />
                                    </flux:table.cell>
                                @endif
                                <flux:table.cell align="end" sticky
                                    class="bg-white dark:bg-zinc-900">
                                    <x-list.row-actions :actions="$row['actions']" prefix="customer"
                                        payload-key="customer" edit-handler="editCustomer"
                                        :blocked-hint="$row['actionHint'] ?? null" />
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <x-list.empty-state :colspan="$list['extraDateHeading'] ? 6 : 5" :message="$list['emptyMessage']">
                                @if ($list['create'] && $list['search']['value'] === '')
                                    <flux:modal.trigger name="customer-form">
                                        <flux:button size="sm" variant="primary" icon="plus"
                                            x-on:click="createCustomer()"
                                            data-test="customer-empty-create">
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
            <flux:modal name="customer-form" aria-labelledby="customer-form-heading"
                variant="flyout" position="right"
                x-on:close="window.clearForm($el.querySelector('form')); window.resetTrackedForms($el)"
                x-on:cancel.prevent="window.requestTrackedModalClose($el)"
                class="form-drawer max-w-none">
                @include('customers.form')
            </flux:modal>

            @if ($deletedCustomerConflict)
                <x-name-conflict-modal name="customer-name-conflict" :title="__('Customer name already in trash')"
                    :message="__('A deleted customer already uses the name :name.', [
                        'name' => $deletedCustomerConflict['name'],
                    ])" :create-action="route('customers.store')" :restore-action="route('customers.trash.restore', $deletedCustomerConflict['id'])" :create-fields="['name' => old('name', $deletedCustomerConflict['name'])]"
                    :create-label="__('Create a new customer')" :restore-label="__('Restore the deleted customer instead')" create-test="customer-conflict-create-new"
                    restore-test="customer-conflict-restore" />
            @endif
        @endif

        <x-list.confirm-modal prefix="customer" />
    </div>
</x-layouts::app>
