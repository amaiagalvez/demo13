<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Basics13\Http\Requests\TrashDestroyRequest;

/**
 * @extends TrashDestroyRequest<Project>
 */
final class ProjectDestroyRequest extends TrashDestroyRequest
{
    /**
     * @return Project
     */
    protected function findTrashed(): Model
    {
        return Project::onlyTrashed()
            ->findOrFail((int) $this->route('project'));
    }
}
