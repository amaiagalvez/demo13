<?php

namespace App\Http\Requests;

use App\Models\Project;

class ProjectSelectOptionsRequest extends SearchableSelectOptionsRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Project::class) ?? false;
    }
}