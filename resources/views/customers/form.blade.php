<x-forms.tracked-resource prefix="customer" context-field="_customer_form" id-field="_customer_id">
    <flux:field>
        <div class="flex items-center gap-1">
            <flux:label>{{ __('Name') }} <span class="text-red-600" aria-hidden="true">*</span>
            </flux:label>
            <flux:tooltip toggleable :content="__('Use at least 4 characters.')">
                <flux:button type="button" icon="information-circle" size="xs" variant="ghost"
                    :aria-label="__('Use at least 4 characters.')"
                    data-test="customer-name-info" />
            </flux:tooltip>
        </div>
        <flux:input name="name" x-model="form.name" minlength="4" maxlength="255" required autofocus
            data-test="customer-name" />
        <flux:error name="name" />
    </flux:field>
</x-forms.tracked-resource>
