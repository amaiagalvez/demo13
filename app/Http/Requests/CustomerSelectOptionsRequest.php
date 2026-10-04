<?php

namespace App\Http\Requests;

use App\Models\Customer;

class CustomerSelectOptionsRequest extends SearchableSelectOptionsRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Customer::class) ?? false;
    }
}