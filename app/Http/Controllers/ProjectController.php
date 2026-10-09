<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Customer;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
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
            'list' => $transformer->active(
                $projects,
                $search,
                $query->stateCounts(activeTotal: $search === '' ? $projects->total() : null),
            ),
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

    public function store(ProjectRequest $request, ProjectListQuery $query): RedirectResponse|JsonResponse
    {
        $name = $request->string('name')->toString();
        $deletedProject = $query->findTrashedByName($name);

        if ($deletedProject && ! $request->boolean('reuse_deleted_name')) {
            return $this->deletedNameConflict($request, $deletedProject);
        }

        try {
            DB::transaction(static function () use ($request): void {
                Customer::query()->lockForUpdate()->findOrFail($request->integer('customer_id'));
                Project::create($request->validated());
            });
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception);
        }

        return to_route('projects.index')->with('status', __('basics13::messages.created'));
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        try {
            DB::transaction(static function () use ($request, $project): void {
                Customer::query()->lockForUpdate()->findOrFail($request->integer('customer_id'));
                $project->update($request->validated());
            });
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception);
        }

        return to_route('projects.index')->with('status', __('basics13::messages.updated'));
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        // The deleting hook holds the row lock and refuses the delete while children exist, so the
        // controller only has to run it inside a transaction and report the outcome.
        $deleted = DB::transaction(static fn (): bool => $project->delete() !== false);

        if (! $deleted) {
            return to_route('projects.index')
                ->with('error', __('Cannot be deleted while it has related records.'));
        }

        return to_route('projects.index')->with('status', __('basics13::messages.moved_to_trash'));
    }
}
