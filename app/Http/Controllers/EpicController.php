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
                'text' => $selectedProject->fullName(),
            ],
            'drawerEpic' => $this->drawerEpic($request, $transformer),
            'list' => $transformer->active(
                $epics,
                $search,
                $query->stateCounts(activeTotal: $search === '' ? $epics->total() : null),
            ),
        ]);
    }

    /**
     * The epic whose drawer has to be reopened after the request: the one just commented, or the one
     * whose edit failed. It is resolved by id instead of being looked up among the current page, so
     * a comment saved on a later page still reopens the right drawer.
     *
     * @return array{id: int, name: string, start_date: string, end_date: string, project_id: int, project_label: string, commentAction: string, commentsUrl: string, commentsCount: int}|null
     */
    private function drawerEpic(EpicListRequest $request, EpicListTransformer $transformer): ?array
    {
        $id = $request->session()->get('commented_epic_id')
            ?? $request->old('_epic_id');

        if (! is_numeric($id)) {
            return null;
        }

        $epic = Epic::query()->with('project.customer')->withCount('comments')->find((int) $id);

        return $epic === null ? null : $transformer->payloadFor($epic);
    }

    public function store(EpicRequest $request, EpicListQuery $query): RedirectResponse|JsonResponse
    {
        $deletedEpic = $query->findTrashedByName(
            $request->string('name')->toString(),
            $request->integer('project_id'),
        );

        if ($deletedEpic && ! $request->boolean('reuse_deleted_name')) {
            return $this->deletedNameConflict($request, $deletedEpic);
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
