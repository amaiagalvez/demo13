<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer
            ? $this->user()->can('update', $customer)
            : $this->user()->can('create', Customer::class);
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
                'max:255',
                Rule::unique(Customer::class)
                    ->ignore($this->route('customer'))
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
