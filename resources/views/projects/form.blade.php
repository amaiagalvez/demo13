<x-forms.tracked-resource prefix="project" context-field="_project_form" id-field="_project_id"
    submit-disabled="isSubmitting || !isDirty || form.customerCreating">
    <flux:field>
        <flux:label>{{ __('Name') }} <span class="text-red-600" aria-hidden="true">*</span>
        </flux:label>
        <flux:input name="name" x-model="form.name" minlength="4" maxlength="255" required
            autofocus data-test="project-name" />
        <flux:error name="name" />
    </flux:field>
    <flux:field>
        <flux:label>{{ __('Start date') }} <span class="text-red-600"
                aria-hidden="true">*</span></flux:label>
        <flux:input type="date" name="start_date" x-model="form.start_date" required
            data-test="project-start-date" />
        <flux:error name="start_date" />
    </flux:field>
    <flux:field>
        <flux:label>{{ __('End date') }}</flux:label>
        <flux:input type="date" name="end_date" x-model="form.end_date"
            x-bind:min="form.start_date" data-test="project-end-date" />
        <flux:error name="end_date" />
    </flux:field>
    <flux:field>
        <flux:label for="project-customer-id">{{ __('Customer') }}
            <span class="text-red-600" aria-hidden="true">*</span>
        </flux:label>
        <select id="project-customer-id" name="customer_id" x-model="form.customer_id"
            x-effect="window.jQuery($el).val(form.customer_id || null).trigger('change.select2')"
            required aria-label="{{ __('Customer') }}" data-test="project-customer"
            data-project-customer-select
            data-customer-store-url="{{ route('customers.store') }}"
            data-create-label="{{ __('Create customer') }}"
            data-create-error="{{ __('Unable to create customer.') }}"
            data-no-results-label="{{ __('No customers found.') }}"
            data-placeholder="{{ __('Select a customer') }}">
            <option></option>
            @foreach ($availableCustomers as $customer)
                <option value="{{ $customer->id }}">{{ $customer->name }}</option>
            @endforeach
        </select>
        <flux:error name="customer_id" />
        <p role="alert" x-show="form.customerCreateError" x-text="form.customerCreateError"
            class="text-sm text-red-600" data-test="project-customer-error"></p>
    </flux:field>
</x-forms.tracked-resource>
