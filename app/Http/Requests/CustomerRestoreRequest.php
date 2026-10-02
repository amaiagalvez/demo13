<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

class CustomerRestoreRequest extends FormRequest
{
    private ?Customer $customer = null;

    public function authorize(): bool
    {
        return $this->user()?->can('restore', $this->customer()) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'resolve_name_conflict' => ['sometimes', 'boolean'],
        ];
    }

    public function customer(): Customer
    {
        return $this->customer ??= Customer::onlyTrashed()
            ->findOrFail((int) $this->route('customer'));
    }
}
