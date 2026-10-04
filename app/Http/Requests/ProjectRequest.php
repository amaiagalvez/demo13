<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Customer;
use Illuminate\Validation\Rule;
use App\Support\Validation\MaxLength;
use Illuminate\Validation\Rules\Exists;
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
                'max:'.MaxLength::string(),
                Rule::unique(Project::class)
                    ->ignore($this->route('project'))
                    ->whereNull('deleted_at'),
            ],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'customer_id' => [
                'required',
                'integer',
                $this->selectableCustomerRule(),
            ],
            'notes' => ['nullable', 'string', 'max:'.MaxLength::longText()],
            'reuse_deleted_name' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * A project may only hang from an active customer, which is what the selector offers. While
     * editing, the customer the project already belongs to stays valid even after it has been
     * deactivated, so a form that does not change it can still be saved.
     */
    protected function selectableCustomerRule(): Exists
    {
        $current = $this->route('project');

        if ($current instanceof Project && $current->customer_id === $this->integer('customer_id')) {
            return Rule::exists(Customer::class, 'id')->whereNull('deleted_at');
        }

        return Rule::exists(Customer::class, 'id')
            ->whereNull('deleted_at')
            ->where('active', true);
    }
}
