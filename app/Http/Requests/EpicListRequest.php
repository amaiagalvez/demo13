<?php

namespace App\Http\Requests;

use App\Models\Epic;
use Illuminate\Foundation\Http\FormRequest;

class EpicListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Epic::class);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function search(): string
    {
        return $this->string('search')->trim()->toString();
    }
}
