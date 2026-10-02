@props([
    'prefix',
    'contextField',
    'idField',
    'submitDisabled' => 'isSubmitting || !isDirty',
])

<div {{ $attributes->merge(['class' => 'mx-auto flex w-full max-w-xl flex-col gap-6']) }}>
    <div>
        <flux:heading size="xl" x-text="form.title"></flux:heading>
        <flux:subheading x-text="form.subtitle"></flux:subheading>
    </div>

    <form method="POST" x-bind:action="form.action"
        x-data="{ isSubmitting: false, isDirty: false }"
        x-on:form-dirty-change="isDirty = $event.detail.isDirty"
        x-on:submit="if (!isDirty || isSubmitting) { $event.preventDefault(); return; } isSubmitting = true"
        x-on:reset="isSubmitting = false" data-track-changes class="flex flex-col gap-6">
        @csrf
        <input type="hidden" name="_method" x-bind:value="form.method">
        <input type="hidden" name="{{ $contextField }}" x-bind:value="form.context">
        <input type="hidden" name="{{ $idField }}" x-bind:value="form.id">

        {{ $slot }}

        <div class="flex justify-end gap-3">
            <flux:modal.close>
                <flux:button type="button" variant="ghost" data-test="{{ $prefix }}-cancel">
                    {{ __('Cancel') }}
                </flux:button>
            </flux:modal.close>
            <flux:button variant="primary" type="submit" disabled
                x-bind:disabled="{{ $submitDisabled }}" x-bind:aria-busy="isSubmitting"
                data-test="{{ $prefix }}-submit">
                <span x-text="form.submitLabel"></span>
            </flux:button>
        </div>
    </form>

    {{ $after ?? '' }}
</div>
