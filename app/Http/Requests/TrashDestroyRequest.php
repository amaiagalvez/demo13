<?php

namespace App\Http\Requests;

use Illuminate\Database\Eloquent\Model;

/**
 * Deletes a trashed record for good. The route carries no body, so only the ability is checked.
 *
 * @template TDeletable of Model
 *
 * @extends TrashRecordRequest<TDeletable>
 */
abstract class TrashDestroyRequest extends TrashRecordRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('forceDelete', $this->record()) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
