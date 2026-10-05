@props(['prefix'])

@if (session('status'))
    <flux:callout class="rounded-xl" icon="check-circle" variant="success" role="status" aria-live="polite"
        x-data="{ visible: true }" x-init="setTimeout(() => visible = false, 20000)" x-show="visible" x-transition.opacity
        :data-test="$prefix.'-status'">{{ session('status') }}</flux:callout>
@endif

@if (session('error'))
    <flux:callout class="rounded-xl" icon="exclamation-triangle" variant="danger" role="alert"
        :data-test="$prefix.'-error'">
        {{ session('error') }}</flux:callout>
@endif
