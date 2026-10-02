<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\View\View;
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
    ): View {
        $search = $request->search();
        $projects = $query->trashed($search);

        return view('projects.list', [
            'projects' => $projects,
            'availableCustomers' => collect(),
            'list' => $transformer->trash($projects, $search),
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

        if ($project->epics()->withTrashed()->exists()) {
            return to_route('projects.trash.index')
                ->with('error', __('Project cannot be permanently deleted while it has epics.'));
        }

        $project->forceDelete();

        return to_route('projects.trash.index')->with('status', __('Project permanently deleted.'));
    }

    private function restoreConflictResponse(): RedirectResponse
    {
        return to_route('projects.trash.index')
            ->with('error', __('Project cannot be restored while another active project uses this name.'));
    }
}
