<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\ProjectListRequest;
use App\Queries\Projects\ProjectListQuery;
use App\Transformers\ProjectListTransformer;
use Basics13\Http\Controllers\ArchivedController;

class ProjectArchivedController extends ArchivedController
{
    public function index(
        ProjectListRequest $request,
        ProjectListQuery $query,
        ProjectListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $projects = $query->archived($search);

        return $this->listView($request, 'projects.list', [
            'projects' => $projects,
            'list' => $transformer->archived(
                $projects,
                $search,
                $query->stateCounts(inactiveTotal: $search === '' ? $projects->total() : null),
            ),
        ]);
    }

    public function archive(Project $project): RedirectResponse
    {
        return $this->archiveRecord($project);
    }

    public function activate(Project $project): RedirectResponse
    {
        return $this->activateRecord($project);
    }

    protected function activeRoute(): string
    {
        return 'projects.index';
    }

    protected function archivedRoute(): string
    {
        return 'projects.archived.index';
    }
}
