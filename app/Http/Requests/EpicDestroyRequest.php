<?php

namespace App\Http\Requests;

use App\Models\Epic;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends TrashDestroyRequest<Epic>
 */
final class EpicDestroyRequest extends TrashDestroyRequest
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
