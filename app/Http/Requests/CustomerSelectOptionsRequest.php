<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Basics13\Http\Requests\SearchableSelectOptionsRequest;

class CustomerSelectOptionsRequest extends SearchableSelectOptionsRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Customer::class) ?? false;
    }
}
