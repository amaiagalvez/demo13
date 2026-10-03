<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\ProjectListRequest;
use App\Queries\Projects\ProjectListQuery;
use App\Transformers\ProjectListTransformer;

class ProjectInactiveController extends Controller
{
    public function index(
        ProjectListRequest $request,
        ProjectListQuery $query,
        ProjectListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $projects = $query->inactive($search);

        return $this->listView($request, 'projects.list', [
            'projects' => $projects,
            'hasCustomers' => false,
            'list' => $transformer->inactive(
                $projects,
                $search,
                $query->stateCounts(inactiveTotal: $search === '' ? $projects->total() : null),
            ),
        ]);
    }

    public function deactivate(Project $project): RedirectResponse
    {
        $this->authorize('deactivate', $project);
        $project->active = false;
        $project->save();

        return to_route('projects.index')->with('status', __('Record deactivated successfully.'));
    }

    public function reactivate(Project $project): RedirectResponse
    {
        $this->authorize('reactivate', $project);
        $project->active = true;
        $project->save();

        return to_route('projects.inactive.index')->with('status', __('Record reactivated successfully.'));
    }
}
