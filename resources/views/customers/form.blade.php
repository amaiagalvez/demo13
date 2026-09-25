<div class="mx-auto flex w-full max-w-xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ $formTitle }}</flux:heading>
        <flux:subheading>{{ $formSubtitle }}</flux:subheading>
    </div>

    <form method="POST" action="{{ $formAction }}" class="flex flex-col gap-6">
        @csrf
        @if ($formMethod !== 'POST')
            @method($formMethod)
        @endif
        <flux:input name="name" :label="__('Name')"
            :value="old('name', $customer->name ?? null)" required autofocus
            data-test="customer-name" />
        <div class="flex justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" type="submit" data-test="customer-submit">
                {{ $submitLabel }}
            </flux:button>
        </div>
    </form>
</div>
