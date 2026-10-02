<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class ProjectRestoreRequest extends FormRequest
{
    private ?Project $project = null;

    public function authorize(): bool
    {
        return $this->user()?->can('restore', $this->project()) ?? false;
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

    public function project(): Project
    {
        return $this->project ??= Project::onlyTrashed()
            ->findOrFail((int) $this->route('project'));
    }
}
