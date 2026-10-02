<?php

namespace App\Http\Requests;

use App\Models\Epic;
use App\Models\Project;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class EpicRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }
    }

    public function authorize(): bool
    {
        $epic = $this->route('epic');

        return $epic
            ? ($this->user()?->can('update', $epic) ?? false)
            : ($this->user()?->can('create', Epic::class) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:4',
                'max:255',
                Rule::unique(Epic::class)
                    ->ignore($this->route('epic'))
                    ->where('project_id', $this->integer('project_id'))
                    ->whereNull('deleted_at'),
            ],
            'start_date' => ['nullable', 'required_with:end_date', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after:start_date'],
            'project_id' => [
                'required',
                'integer',
                Rule::exists(Project::class, 'id')->whereNull('deleted_at'),
            ],
            'reuse_deleted_name' => ['sometimes', 'boolean'],
        ];
    }
}
