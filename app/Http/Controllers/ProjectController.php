<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Customer;
use Illuminate\View\View;
use App\Http\Requests\ProjectRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;
use App\Http\Requests\ProjectListRequest;
use App\Queries\Projects\ProjectListQuery;
use App\Transformers\ProjectListTransformer;
use App\Support\Database\UniqueConstraintViolation;

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
        $name = $request->string('name')->toString();
        $deletedProject = Project::onlyTrashed()
            ->where('name', $name)
            ->latest('deleted_at')
            ->first();

        if ($deletedProject && ! $request->boolean('reuse_deleted_name')) {
            return to_route('projects.index')
                ->withInput()
                ->with('deleted_project_conflict', [
                    'id' => $deletedProject->id,
                    'name' => $deletedProject->name,
                ]);
        }

        try {
            Project::create($request->validated());
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception);
        }

        return to_route('projects.index')->with('status', __('Project created successfully.'));
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        try {
            $project->update($request->validated());
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception);
        }

        return to_route('projects.index')->with('status', __('Project updated successfully.'));
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        if ($project->epics()->withTrashed()->exists()) {
            return to_route('projects.index')
                ->with('error', __('Project cannot be deleted while it has epics.'));
        }

        $project->delete();

        return to_route('projects.index')->with('status', __('Project moved to trash.'));
    }
}
