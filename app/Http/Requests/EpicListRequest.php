<?php

namespace App\Http\Requests;

use App\Models\Epic;
use Basics13\Http\Requests\SearchableListRequest;

class EpicListRequest extends SearchableListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Epic::class) ?? false;
    }
}
