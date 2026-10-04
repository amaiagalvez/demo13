<x-forms.tracked-resource prefix="project" context-field="_project_form" id-field="_project_id"
    submit-disabled="isSubmitting || !isDirty || form.customerCreating">
    <flux:field>
        <div class="flex items-center gap-1">
            <flux:label>{{ __('Name') }} <span class="text-red-600" aria-hidden="true">*</span>
            </flux:label>
            <flux:tooltip toggleable :content="__('Use at least 4 characters.')">
                <flux:button type="button" icon="information-circle" size="xs" variant="ghost"
                    :aria-label="__('Use at least 4 characters.')" data-test="project-name-info" />
            </flux:tooltip>
        </div>
        <flux:input name="name" x-model="form.name" minlength="4"
            maxlength="{{ \App\Support\Validation\MaxLength::string() }}" required
            autofocus data-test="project-name" />
        <flux:error name="name" />
    </flux:field>
    <flux:field>
        <flux:label>{{ __('Start date') }} <span class="text-red-600" aria-hidden="true">*</span>
        </flux:label>
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
            x-effect="
                const customerId = String(form.customer_id || '');
                if (customerId && form.customer_name) {
                    let option = Array.from($el.options).find((item) => item.value === customerId);
                    if (!option) {
                        option = new Option(form.customer_name, customerId, true, true);
                        $el.add(option);
                    }
                    option.selected = true;
                }
                window.jQuery($el).val(customerId || null).trigger('change.select2')
            "
            required data-test="project-customer" data-project-customer-select
            data-customer-store-url="{{ route('customers.store', [], false) }}"
            data-customer-search-url="{{ route('customers.options', [], false) }}"
            data-create-label="{{ __('Create customer') }}"
            data-create-error="{{ __('Unable to create customer.') }}"
            data-search-error="{{ __('Unable to load customers.') }}"
            data-no-results-label="{{ __('No customers found.') }}"
            data-placeholder="{{ __('Select a customer') }}">
            <option></option>
            @if ($selectedCustomer)
                <option value="{{ $selectedCustomer->id }}">{{ $selectedCustomer->name }}</option>
            @endif
        </select>
        <flux:error name="customer_id" />
        <p role="alert" x-show="form.customerCreateError" x-text="form.customerCreateError"
            class="text-sm text-red-600" data-test="project-customer-error"></p>
    </flux:field>
    <flux:field>
        <flux:label>{{ __('Notes') }}</flux:label>
        <flux:textarea name="notes" x-model="form.notes" rows="3"
            maxlength="{{ \App\Support\Validation\MaxLength::longText() }}"
            data-test="project-notes" />
        <flux:error name="notes" />
    </flux:field>
</x-forms.tracked-resource>
