<?php

namespace App\Http\Requests;

use App\Models\Epic;
use Illuminate\Database\Eloquent\Model;
use Basics13\Http\Requests\RestoreRequest;

/**
 * @extends RestoreRequest<Epic>
 */
final class EpicRestoreRequest extends RestoreRequest
{
    /**
     * @return Epic
     */
    protected function findTrashed(): Model
    {
        return Epic::onlyTrashed()
            ->findOrFail((int) $this->route('epic'));
    }
}
