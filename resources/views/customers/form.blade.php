<x-forms.tracked-resource prefix="customer" context-field="_customer_form" id-field="_customer_id">
    <flux:field>
        <flux:label>{{ __('Name') }} <span class="text-red-600" aria-hidden="true">*</span>
        </flux:label>
        <flux:input name="name" x-model="form.name" maxlength="255" required autofocus
            data-test="customer-name" />
        <flux:error name="name" />
    </flux:field>
</x-forms.tracked-resource>
