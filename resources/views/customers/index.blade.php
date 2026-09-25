<x-layouts::app :title="__('Customers')">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('Customers') }}</flux:heading>
                <flux:subheading>{{ __('Manage your customers.') }}</flux:subheading>
            </div>
            <flux:modal.trigger name="customer-create">
                <flux:button variant="primary" icon="plus">
                    {{ __('New customer') }}
                </flux:button>
            </flux:modal.trigger>
        </div>

        @if (session('status'))
            <flux:callout icon="check-circle" variant="success">{{ session('status') }}</flux:callout>
        @endif

        <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
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
                                    <flux:modal.trigger name="customer-edit-{{ $customer->id }}">
                                        <flux:button size="sm" variant="ghost"
                                            icon="pencil-square">
                                            {{ __('Edit') }}
                                        </flux:button>
                                    </flux:modal.trigger>
                                    <form method="POST"
                                        action="{{ route('customers.destroy', $customer) }}">
                                        @csrf
                                        @method('DELETE')
                                        <flux:button size="sm" variant="ghost" icon="trash"
                                            type="submit" class="text-red-600">
                                            {{ __('Delete') }}
                                        </flux:button>
                                    </form>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="py-8 text-center text-zinc-500">
                                {{ __('No customers yet.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    <flux:modal name="customer-create" variant="flyout" position="right"
        class="customer-drawer max-w-none">
        @include('customers._form', ['customer' => null, 'inDrawer' => true])
    </flux:modal>

    @foreach ($customers as $customer)
        <flux:modal name="customer-edit-{{ $customer->id }}" variant="flyout" position="right"
            class="customer-drawer max-w-none">
            @include('customers._form', ['customer' => $customer, 'inDrawer' => true])
        </flux:modal>
    @endforeach
</x-layouts::app>
