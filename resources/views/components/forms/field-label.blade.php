@props([
    'request',
    'field',
    'test',
    'for' => null,
    'labels' => [],
    'notices' => [],
])

{{--
    The label of a form field with the info icon that lists what the form asks of that field. The red
    asterisk and the notices derived from the rules come from the request, so the form cannot promise
    less or more than the server asks for.

    :request  the FormRequest class whose rules describe the field.
    :field    the field name inside those rules.
    :test     data-test of the info button, "{prefix}-{field}-info".
    :for      id of the control, for the fields that carry one.
    :labels   labels of the other fields of the form, for a rule that names them.
    :notices  what the control itself offers, for the things a rule cannot say, already translated.
--}}

@php
    $validations = new \Basics13\Support\Validation\FieldHints($request, $labels);
    $tooltip = trim(implode(' ', [...$validations->for($field), ...array_filter($notices)]));
@endphp

<div class="flex items-center gap-1">
    <flux:label :for="$for" class="gap-1">
        <span>{{ $slot }}</span>
        @if ($validations->isRequired($field))
            <span class="text-red-600" aria-hidden="true">*</span>
        @endif
    </flux:label>
    @if ($tooltip !== '')
        <flux:tooltip toggleable :content="$tooltip">
            <flux:button type="button" icon="information-circle" size="xs" variant="ghost"
                :aria-label="$tooltip" data-test="{{ $test }}" />
        </flux:tooltip>
    @endif
</div>
