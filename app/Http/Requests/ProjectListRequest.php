<?php

namespace App\Http\Requests;

use App\Models\Project;
use Basics13\Http\Requests\SearchableListRequest;

class ProjectListRequest extends SearchableListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Project::class) ?? false;
    }
}
