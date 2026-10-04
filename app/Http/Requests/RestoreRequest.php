<?php

namespace App\Http\Requests;

use Illuminate\Database\Eloquent\Model;

/**
 * Puts a trashed record back among the active ones.
 *
 * @template TRestoreable of Model
 *
 * @extends TrashRecordRequest<TRestoreable>
 */
abstract class RestoreRequest extends TrashRecordRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('restore', $this->record()) ?? false;
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
}
