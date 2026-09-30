<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Customer;
use Illuminate\View\View;
use App\Http\Requests\ProjectRequest;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\ProjectListRequest;
use App\Queries\Projects\ProjectListQuery;
use App\Transformers\ProjectListTransformer;

class ProjectController extends Controller
{
    public function index(
        ProjectListRequest $request,
        ProjectListQuery $query,
        ProjectListTransformer $transformer,
    ): View {
        $search = $request->search();
        $projects = $query->active($search);

        return view('projects.list', [
            'projects' => $projects,
            'availableCustomers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'list' => $transformer->active($projects, $search),
        ]);
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        Project::create($request->validated());

        return to_route('projects.index')->with('status', __('Project created successfully.'));
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        return to_route('projects.index')->with('status', __('Project updated successfully.'));
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);
        $project->delete();

        return to_route('projects.index')->with('status', __('Project moved to trash.'));
    }
}
