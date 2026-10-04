<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Validation\Rule;
use App\Support\Validation\MaxLength;
use Illuminate\Foundation\Http\FormRequest;

class CustomerRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }
    }

    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer
            ? ($this->user()?->can('update', $customer) ?? false)
            : ($this->user()?->can('create', Customer::class) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:4',
                'max:'.MaxLength::string(),
                Rule::unique(Customer::class)
                    ->ignore($this->route('customer'))
                    ->whereNull('deleted_at'),
            ],
            'notes' => ['nullable', 'string', 'max:'.MaxLength::longText()],
            'reuse_deleted_name' => ['sometimes', 'boolean'],
        ];
    }
}
