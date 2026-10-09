<x-basics13::forms.tracked-resource prefix="customer" context-field="_customer_form" id-field="_customer_id">
    <flux:field>
        <x-forms.field-label :request="\App\Http\Requests\CustomerRequest::class" field="name"
            test="customer-name-info" for="customer-name">
            {{ __('Name') }}
        </x-forms.field-label>
        <flux:input name="name" x-model="form.name" minlength="4"
            maxlength="{{ \Basics13\Support\Validation\MaxLength::string() }}" required autofocus
            id="customer-name" data-test="customer-name" />
        <flux:error name="name" />
    </flux:field>
    <flux:field>
        <x-forms.field-label :request="\App\Http\Requests\CustomerRequest::class" field="notes"
            test="customer-notes-info" for="customer-notes">
            {{ __('Notes') }}
        </x-forms.field-label>
        <flux:textarea name="notes" x-model="form.notes" rows="3"
            maxlength="{{ \Basics13\Support\Validation\MaxLength::longText() }}"
            id="customer-notes" data-test="customer-notes" />
        <flux:error name="notes" />
    </flux:field>
</x-basics13::forms.tracked-resource>
