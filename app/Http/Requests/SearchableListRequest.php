<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class SearchableListRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    final public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }

    final public function search(): string
    {
        return $this->string('search')->trim()->toString();
    }
}
