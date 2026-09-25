@php($isEditing = isset($customer))
@php($inDrawer = $inDrawer ?? false)

<div class="mx-auto flex w-full max-w-xl flex-col gap-6">
    <div>
        <flux:heading size="xl">
            {{ $isEditing ? __('Edit customer') : __('New customer') }}
        </flux:heading>
        <flux:subheading>
            {{ $isEditing ? __('Update the customer details.') : __('Add a customer to your records.') }}
        </flux:subheading>
    </div>

    <form method="POST"
        action="{{ $isEditing ? route('customers.update', $customer) : route('customers.store') }}"
        class="flex flex-col gap-6">
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif
        <flux:input name="name" :label="__('Name')"
            :value="old('name', $customer->name ?? null)" required autofocus />
        <div class="flex justify-end gap-3">
            @if ($inDrawer)
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
            @else
                <flux:button variant="ghost" :href="route('customers.index')" wire:navigate>
                    {{ __('Cancel') }}</flux:button>
            @endif
            <flux:button variant="primary" type="submit">
                {{ $isEditing ? __('Update customer') : __('Save customer') }}
            </flux:button>
        </div>
    </form>
</div>
