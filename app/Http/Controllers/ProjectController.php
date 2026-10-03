<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Customer;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\ProjectRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;
use App\Http\Requests\ProjectListRequest;
use App\Queries\Projects\ProjectListQuery;
use App\Transformers\ProjectListTransformer;
use App\Http\Requests\ProjectSelectOptionsRequest;
use App\Queries\Projects\ProjectSelectOptionsQuery;
use App\Support\Database\UniqueConstraintViolation;

class ProjectController extends Controller
{
    public function index(
        ProjectListRequest $request,
        ProjectListQuery $query,
        ProjectListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $projects = $query->active($search);
        $customerId = old('customer_id');
        $selectedCustomer = is_numeric($customerId)
            ? Customer::query()->find((int) $customerId, ['id', 'name'])
            : null;

        return $this->listView($request, 'projects.list', [
            'projects' => $projects,
            'hasCustomers' => Customer::query()->where('active', true)->exists(),
            'selectedCustomer' => $selectedCustomer,
            'list' => $transformer->active($projects, $search),
        ]);
    }

    public function selectOptions(
        ProjectSelectOptionsRequest $request,
        ProjectSelectOptionsQuery $query,
    ): JsonResponse {
        return response()->json([
            'results' => $query->search($request->search()),
        ]);
    }

    public function store(ProjectRequest $request, ProjectListQuery $query): RedirectResponse
    {
        $name = $request->string('name')->toString();
        $deletedProject = $query->findTrashedByName($name);

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

        if ($project->delete() === false) {
            return to_route('projects.index')
                ->with('error', __('Project cannot be deleted while it has epics.'));
        }

        return to_route('projects.index')->with('status', __('Project moved to trash.'));
    }
}
