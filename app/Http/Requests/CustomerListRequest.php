<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Basics13\Http\Requests\SearchableListRequest;

class CustomerListRequest extends SearchableListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Customer::class) ?? false;
    }
}
