<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\ProjectListRequest;
use App\Queries\Projects\ProjectListQuery;
use App\Transformers\ProjectListTransformer;

class ProjectInactiveController extends InactiveController
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
            'list' => $transformer->inactive(
                $projects,
                $search,
                $query->stateCounts(inactiveTotal: $search === '' ? $projects->total() : null),
            ),
        ]);
    }

    public function deactivate(Project $project): RedirectResponse
    {
        return $this->deactivateRecord($project);
    }

    public function reactivate(Project $project): RedirectResponse
    {
        return $this->reactivateRecord($project);
    }

    protected function activeRoute(): string
    {
        return 'projects.index';
    }

    protected function inactiveRoute(): string
    {
        return 'projects.inactive.index';
    }
}
