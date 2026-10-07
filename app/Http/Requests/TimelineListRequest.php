<?php

namespace App\Http\Requests;

use App\Models\Customer;

class TimelineListRequest extends SearchableListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Customer::class) ?? false;
    }
}
