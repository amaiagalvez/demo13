<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;
use App\Http\Requests\ProjectListRequest;
use App\Queries\Projects\ProjectListQuery;
use App\Http\Requests\ProjectRestoreRequest;
use App\Transformers\ProjectListTransformer;
use App\Support\Database\UniqueConstraintViolation;

class ProjectTrashController extends Controller
{
    public function index(
        ProjectListRequest $request,
        ProjectListQuery $query,
        ProjectListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $projects = $query->trashed($search);

        return $this->listView($request, 'projects.list', [
            'projects' => $projects,
            'hasCustomers' => false,
            'list' => $transformer->trash($projects, $search, $query->stateCounts()),
        ]);
    }

    public function restore(ProjectRestoreRequest $request): RedirectResponse
    {
        $project = $request->project();

        if (Project::query()->where('name', $project->name)->exists()) {
            return $this->restoreConflictResponse();
        }

        try {
            $project->restore();
        } catch (QueryException $exception) {
            if (! UniqueConstraintViolation::causedBy($exception)) {
                throw $exception;
            }

            return $this->restoreConflictResponse();
        }

        $message = $request->boolean('resolve_name_conflict')
            ? __('Project restored successfully. No new project was created with the repeated name.')
            : __('Project restored successfully.');

        return to_route('projects.trash.index')->with('status', $message);
    }

    public function destroy(int $project): RedirectResponse
    {
        $project = Project::onlyTrashed()->findOrFail($project);
        $this->authorize('forceDelete', $project);

        $deleted = DB::transaction(static function () use ($project): bool {
            $lockedProject = Project::onlyTrashed()
                ->whereKey($project->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            return $lockedProject->forceDelete() !== false;
        });

        if (! $deleted) {
            return to_route('projects.trash.index')
                ->with('error', __('Project cannot be permanently deleted while it has epics.'));
        }

        return to_route('projects.trash.index')->with('status', __('Project permanently deleted.'));
    }

    private function restoreConflictResponse(): RedirectResponse
    {
        return to_route('projects.trash.index')
            ->with('error', __('Project cannot be restored because another project outside the trash uses this name.'));
    }
}
