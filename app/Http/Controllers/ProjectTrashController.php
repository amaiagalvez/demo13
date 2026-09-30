<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\ProjectListRequest;
use App\Queries\Projects\ProjectListQuery;
use App\Transformers\ProjectListTransformer;

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

    public function restore(int $project): RedirectResponse
    {
        $project = Project::onlyTrashed()->findOrFail($project);
        $this->authorize('restore', $project);
        $project->restore();

        return to_route('projects.trash.index')->with('status', __('Project restored successfully.'));
    }

    public function destroy(int $project): RedirectResponse
    {
        $project = Project::onlyTrashed()->findOrFail($project);
        $this->authorize('forceDelete', $project);
        $project->forceDelete();

        return to_route('projects.trash.index')->with('status', __('Project permanently deleted.'));
    }
}
