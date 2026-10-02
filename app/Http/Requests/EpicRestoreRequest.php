<?php

namespace App\Http\Requests;

use App\Models\Epic;
use Illuminate\Foundation\Http\FormRequest;

class EpicRestoreRequest extends FormRequest
{
    private ?Epic $epic = null;

    public function authorize(): bool
    {
        return $this->user()?->can('restore', $this->epic()) ?? false;
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

    public function epic(): Epic
    {
        return $this->epic ??= Epic::onlyTrashed()
            ->findOrFail((int) $this->route('epic'));
    }
}
