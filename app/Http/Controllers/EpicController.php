<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use App\Models\Project;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\EpicRequest;
use Illuminate\Support\Facades\DB;
use App\Queries\Epics\EpicListQuery;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\EpicListRequest;
use Illuminate\Database\QueryException;
use App\Transformers\EpicListTransformer;
use App\Support\Database\UniqueConstraintViolation;

class EpicController extends Controller
{
    public function index(
        EpicListRequest $request,
        EpicListQuery $query,
        EpicListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $epics = $query->active($search);
        $projectId = old('project_id');
        $selectedProject = is_numeric($projectId)
            ? Project::query()
                ->with('customer:id,name')
                ->find((int) $projectId, ['id', 'name', 'customer_id'])
            : null;

        return $this->listView($request, 'epics.list', [
            'epics' => $epics,
            'hasProjects' => Project::query()->where('active', true)->exists(),
            'selectedProjectOption' => $selectedProject === null ? null : [
                'id' => $selectedProject->id,
                'text' => $selectedProject->name.' ('.($selectedProject->customer->name ?? '—').')',
            ],
            'list' => $transformer->active(
                $epics,
                $search,
                $query->stateCounts(activeTotal: $search === '' ? $epics->total() : null),
            ),
        ]);
    }

    public function store(EpicRequest $request, EpicListQuery $query): RedirectResponse
    {
        $deletedEpic = $query->findTrashedByName(
            $request->string('name')->toString(),
            $request->integer('project_id'),
        );

        if ($deletedEpic && ! $request->boolean('reuse_deleted_name')) {
            return to_route('epics.index')
                ->withInput()
                ->with('deleted_epic_conflict', [
                    'id' => $deletedEpic->id,
                    'name' => $deletedEpic->name,
                ]);
        }

        try {
            DB::transaction(static function () use ($request): void {
                Project::query()->lockForUpdate()->findOrFail($request->integer('project_id'));
                Epic::create($request->validated());
            });
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception);
        }

        return to_route('epics.index')->with('status', __('Record created successfully.'));
    }

    public function update(EpicRequest $request, Epic $epic): RedirectResponse
    {
        try {
            DB::transaction(static function () use ($request, $epic): void {
                Project::query()->lockForUpdate()->findOrFail($request->integer('project_id'));
                $epic->update($request->validated());
            });
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception);
        }

        return to_route('epics.index')->with('status', __('Record updated successfully.'));
    }

    public function destroy(Epic $epic): RedirectResponse
    {
        $this->authorize('delete', $epic);
        $epic->delete();

        return to_route('epics.index')->with('status', __('Record moved to trash.'));
    }
}
