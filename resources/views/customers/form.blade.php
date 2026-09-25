@php($usesSelectedCustomer = $usesSelectedCustomer ?? false)

<div class="mx-auto flex w-full max-w-xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ $formTitle }}</flux:heading>
        <flux:subheading>{{ $formSubtitle }}</flux:subheading>
    </div>

    <form method="POST"
        @if ($usesSelectedCustomer) x-bind:action="customerUrl(updateUrl)" @else action="{{ $formAction }}" @endif
        x-data="{ isSubmitting: false }"
        x-on:submit="if (isSubmitting) $event.preventDefault(); isSubmitting = true"
        class="flex flex-col gap-6">
        @csrf
        @if ($formMethod !== 'POST')
            @method($formMethod)
        @endif
        @if ($usesSelectedCustomer)
            <input type="hidden" name="_customer_form" x-bind:value="`edit-${customer.id}`">
            <input type="hidden" name="_customer_id" x-bind:value="customer.id">
            <flux:input name="name" :label="__('Name')" x-model="customer.name"
                maxlength="255" required autofocus data-test="customer-name" />
        @else
            <input type="hidden" name="_customer_form" value="{{ $formContext }}">
            <flux:input name="name" :label="__('Name')"
                :value="old('_customer_form') === $formContext ? old('name') : $customer?->name"
                maxlength="255" required autofocus data-test="customer-name" />
        @endif
        <div class="flex justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" type="submit" x-bind:disabled="isSubmitting"
                x-bind:aria-busy="isSubmitting" data-test="customer-submit">
                {{ $submitLabel }}
            </flux:button>
        </div>
    </form>
</div>
