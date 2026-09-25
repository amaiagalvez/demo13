<x-layouts::app :title="__('Customer trash')">
    <div x-data="{
        customer: { id: null, name: '' },
        destroyUrl: @js(route('customers.trash.destroy', '__CUSTOMER__')),
        selectCustomer(customer) {
            this.customer = customer;
        },
        customerUrl() {
            return this.destroyUrl.replace('__CUSTOMER__', this.customer.id);
        },
    }" class="flex flex-col gap-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('Customer trash') }}</flux:heading>
                <flux:subheading>{{ __('Restore customers or delete them permanently.') }}
                </flux:subheading>
            </div>
            <flux:button :href="route('customers.index')" variant="ghost" icon="arrow-left"
                wire:navigate>
                {{ __('Customers') }}
            </flux:button>
        </div>

        @if (session('status'))
            <flux:callout icon="check-circle" variant="success">{{ session('status') }}</flux:callout>
        @endif

        <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Deleted at') }}</flux:table.column>
                    <flux:table.column class="text-end">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($customers as $customer)
                        <flux:table.row :key="$customer->id">
                            <flux:table.cell class="font-medium">{{ $customer->name }}
                            </flux:table.cell>
                            <flux:table.cell>{{ $customer->deleted_at->format('Y-m-d') }}
                            </flux:table.cell>
                            <flux:table.cell class="text-end">
                                <div class="flex justify-end gap-2">
                                    <form method="POST"
                                        action="{{ route('customers.trash.restore', $customer->id) }}"
                                        x-data="{ isSubmitting: false }"
                                        x-on:submit="if (isSubmitting) $event.preventDefault(); isSubmitting = true">
                                        @csrf
                                        @method('PATCH')
                                        <flux:button type="submit" size="sm" variant="ghost"
                                            icon="arrow-path" x-bind:disabled="isSubmitting"
                                            data-test="customer-restore-{{ $customer->id }}">
                                            {{ __('Restore') }}
                                        </flux:button>
                                    </form>
                                    <flux:modal.trigger name="customer-force-delete">
                                        <flux:button size="sm" variant="ghost" icon="trash"
                                            class="text-red-600"
                                            x-on:click="selectCustomer(@js($customer->only(['id', 'name'])))"
                                            data-test="customer-force-delete-{{ $customer->id }}">
                                            {{ __('Delete permanently') }}
                                        </flux:button>
                                    </flux:modal.trigger>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="py-8 text-center text-zinc-500">
                                {{ __('Trash is empty.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        {{ $customers->links() }}

        <flux:modal name="customer-force-delete" class="max-w-md">
            <div class="flex flex-col gap-6">
                <div>
                    <flux:heading size="lg">{{ __('Permanently delete customer?') }}
                    </flux:heading>
                    <flux:text class="mt-2">{{ __('This action cannot be undone.') }}</flux:text>
                </div>

                <div class="flex justify-end gap-3">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <form method="POST" x-bind:action="customerUrl()" x-data="{ isSubmitting: false }"
                        x-on:submit="if (isSubmitting) $event.preventDefault(); isSubmitting = true">
                        @csrf
                        @method('DELETE')
                        <flux:button variant="danger" type="submit" x-bind:disabled="isSubmitting"
                            data-test="customer-force-delete-confirm">
                            {{ __('Delete permanently') }}
                        </flux:button>
                    </form>
                </div>
            </div>
        </flux:modal>
    </div>
</x-layouts::app>
