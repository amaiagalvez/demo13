<?php

namespace App\Http\Requests;

use App\Models\Epic;

class EpicListRequest extends SearchableListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Epic::class) ?? false;
    }
}
