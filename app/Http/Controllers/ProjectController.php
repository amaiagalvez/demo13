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
use Illuminate\Validation\ValidationException;

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
        try {
            Project::create($request->validated());
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'name' => __('validation.unique', ['attribute' => __('Name')]),
            ]);
        }

        return to_route('projects.index')->with('status', __('Project created successfully.'));
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        try {
            $project->update($request->validated());
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'name' => __('validation.unique', ['attribute' => __('Name')]),
            ]);
        }

        return to_route('projects.index')->with('status', __('Project updated successfully.'));
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);
        $project->delete();

        return to_route('projects.index')->with('status', __('Project moved to trash.'));
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;

        return ($errorInfo[0] ?? $exception->getCode()) === '23000'
            && in_array((int) ($errorInfo[1] ?? 0), [19, 1062], true);
    }
}
