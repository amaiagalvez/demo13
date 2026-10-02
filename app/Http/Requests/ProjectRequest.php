<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Customer;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class ProjectRequest extends FormRequest
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
        $project = $this->route('project');

        return $project
            ? ($this->user()?->can('update', $project) ?? false)
            : ($this->user()?->can('create', Project::class) ?? false);
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
                Rule::unique(Project::class)
                    ->ignore($this->route('project'))
                    ->whereNull('deleted_at'),
            ],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'customer_id' => [
                'required',
                'integer',
                Rule::exists(Customer::class, 'id')->whereNull('deleted_at'),
            ],
            'reuse_deleted_name' => ['sometimes', 'boolean'],
        ];
    }
}
