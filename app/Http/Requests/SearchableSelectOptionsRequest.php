<?php

namespace App\Http\Requests;

use App\Support\Validation\MaxLength;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared rules and normalisation for the remote select-options endpoints. Only `authorize()`
 * depends on the resource, so a concrete request names its model and nothing else.
 */
abstract class SearchableSelectOptionsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    final public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:'.MaxLength::string()],
        ];
    }

    final public function search(): string
    {
        return $this->string('q')->trim()->toString();
    }
}
