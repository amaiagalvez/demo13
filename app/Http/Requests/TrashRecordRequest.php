<?php

namespace App\Http\Requests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Resolves the trashed record a trash route points at. Both verbs of the trash route address the
 * same record, so the record is resolved once here and each verb authorizes its own ability.
 *
 * @template TRestoreable of Model
 */
abstract class TrashRecordRequest extends FormRequest
{
    /** @var TRestoreable|null */
    private ?Model $record = null;

    /**
     * The trashed record this request acts on.
     *
     * @return TRestoreable
     */
    public function record(): Model
    {
        return $this->record ??= $this->findTrashed();
    }

    /**
     * Every resource routed through a trash controller is soft deletable, which the model returned
     * here guarantees.
     *
     * @return TRestoreable
     */
    abstract protected function findTrashed(): Model;
}
