@php
    $editingCustomer = $list['create'] && str_starts_with(old('_customer_form', ''), 'edit-');
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

<x-layouts::app :title="$list['title']">
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
        @if ($errors->any()) x-init="$nextTick(() => $dispatch('modal-show', { name: 'customer-form' }))" @endif
        class="flex flex-col gap-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ $list['title'] }}</flux:heading>
                <flux:subheading>{{ $list['subtitle'] }}</flux:subheading>
            </div>
            <div class="flex items-center gap-2">
                <flux:button :href="$list['navigation']['url']" variant="ghost"
                    :icon="$list['navigation']['icon']" wire:navigate
                    :data-test="$list['navigation']['test']">
                    {{ $list['navigation']['label'] }}
                </flux:button>
                @if ($list['create'])
                    <flux:modal.trigger name="customer-form">
                        <flux:button variant="primary" icon="plus" x-on:click="createCustomer()"
                            data-test="customer-create-button">
                            {{ __('New customer') }}
                        </flux:button>
                    </flux:modal.trigger>
                @endif
            </div>
        </div>

        @if (session('status'))
            <flux:callout icon="check-circle" variant="success" x-data="{ visible: true }"
                x-init="setTimeout(() => visible = false, 10000)" x-show="visible" x-transition.opacity
                data-test="customer-status">{{ session('status') }}</flux:callout>
        @endif

        <form method="GET" action="{{ $list['search']['action'] }}"
            class="flex w-full items-end gap-2 sm:max-w-xl">
            <flux:input name="search" :label="__('Search')"
                :placeholder="$list['search']['placeholder']" :value="$list['search']['value']"
                maxlength="255" icon="magnifying-glass" data-test="customer-search" />
            <flux:button type="submit" variant="primary" icon="magnifying-glass"
                data-test="customer-search-submit">
                {{ __('Search') }}
            </flux:button>
            @if ($list['search']['value'] !== '')
                <flux:button :href="$list['search']['action']" variant="ghost" icon="x-mark"
                    wire:navigate data-test="customer-search-clear">
                    {{ __('Clear') }}
                </flux:button>
            @endif
        </form>

        <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ $list['dateHeading'] }}</flux:table.column>
                    <flux:table.column class="text-end">
                        <span class="sr-only">{{ __('Actions') }}</span>
                    </flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($list['rows'] as $row)
                        <flux:table.row :key="$row['id']">
                            <flux:table.cell class="font-medium">{{ $row['name'] }}
                            </flux:table.cell>
                            <flux:table.cell>{{ $row['date'] }}</flux:table.cell>
                            <flux:table.cell class="text-end">
                                <div class="flex justify-end gap-2">
                                    @foreach ($row['actions'] as $action)
                                        @if ($action['type'] === 'form-modal')
                                            <flux:modal.trigger name="customer-form">
                                                <flux:tooltip :content="$action['label']">
                                                    <flux:button size="sm" variant="ghost"
                                                        :icon="$action['icon']"
                                                        :aria-label="$action['label']"
                                                        x-on:click="editCustomer(JSON.parse($el.dataset.customer))"
                                                        data-customer="{{ json_encode($action['customer']) }}"
                                                        :data-test="$action['test']" />
                                                </flux:tooltip>
                                            </flux:modal.trigger>
                                        @else
                                            <flux:modal.trigger name="customer-confirm">
                                                <flux:tooltip :content="$action['label']">
                                                    <flux:button size="sm" variant="ghost"
                                                        :icon="$action['icon']"
                                                        :aria-label="$action['label']"
                                                        :class="$action['danger'] ?? false ?
                                                            'text-red-600' : ''"
                                                        x-on:click="confirmAction(JSON.parse($el.dataset.action))"
                                                        data-action="{{ json_encode($action) }}"
                                                        :data-test="$action['test']" />
                                                </flux:tooltip>
                                            </flux:modal.trigger>
                                        @endif
                                    @endforeach
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="py-8 text-center text-zinc-500">
                                {{ $list['emptyMessage'] }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        @if ($customers->hasPages())
            <nav aria-label="{{ __('Pagination') }}" data-test="customer-pagination">
                {{ $customers->links() }}
            </nav>
        @endif

        @if ($list['create'])
            <flux:modal name="customer-form" variant="flyout" position="right"
                x-on:close="window.clearForm($el.querySelector('form'))"
                class="customer-drawer max-w-none">
                @include('customers.form')
            </flux:modal>
        @endif

        <flux:modal name="customer-confirm" class="max-w-md">
            <div class="flex flex-col gap-6">
                <div>
                    <flux:heading size="lg" x-text="confirmation.title"></flux:heading>
                    <flux:text class="mt-2" x-text="confirmation.text"></flux:text>
                </div>

                <div class="flex justify-end gap-3">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <form method="POST" x-bind:action="confirmation.action"
                        x-data="{ isSubmitting: false }"
                        x-on:submit="if (isSubmitting) $event.preventDefault(); isSubmitting = true">
                        @csrf
                        <input type="hidden" name="_method" x-bind:value="confirmation.method">
                        <template x-if="confirmation.danger">
                            <flux:button variant="danger" type="submit"
                                x-bind:disabled="isSubmitting" data-test="customer-confirm-submit">
                                <span x-text="confirmation.label"></span>
                            </flux:button>
                        </template>
                        <template x-if="!confirmation.danger">
                            <flux:button variant="primary" type="submit"
                                x-bind:disabled="isSubmitting" data-test="customer-confirm-submit">
                                <span x-text="confirmation.label"></span>
                            </flux:button>
                        </template>
                    </form>
                </div>
            </div>
        </flux:modal>
    </div>
</x-layouts::app>
