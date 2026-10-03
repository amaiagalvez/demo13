{{--
    Primary action of a resource list: a labelled button that opens the "{prefix}-form" modal and
    hands the empty form over to the Alpine handler that fills it.
--}}
@props(['prefix', 'label', 'click'])

<flux:modal.trigger :name="$prefix.'-form'">
    <flux:button variant="primary" icon="plus" :data-test="$prefix.'-create-button'"
        x-on:click="{{ $click }}" {{ $attributes }}>
        {{ $label }}
    </flux:button>
</flux:modal.trigger>
