@props(['prefix'])

@if (session('status'))
    <flux:callout icon="check-circle" variant="success" x-data="{ visible: true }"
        x-init="setTimeout(() => visible = false, 10000)" x-show="visible" x-transition.opacity
        :data-test="$prefix.'-status'">{{ session('status') }}</flux:callout>
@endif

@if (session('error'))
    <flux:callout icon="exclamation-triangle" variant="danger" :data-test="$prefix.'-error'">
        {{ session('error') }}</flux:callout>
@endif
