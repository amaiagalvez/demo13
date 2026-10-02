<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use App\Models\Project;
use Illuminate\View\View;
use App\Http\Requests\EpicRequest;
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

        return view('epics.list', [
            'epics' => $epics,
            'availableProjects' => Project::query()->with('customer')->orderBy('name')->get(['id', 'name', 'customer_id']),
            'list' => $transformer->active($epics, $search),
        ])->fragmentIf($request->hasHeader('X-List-Fragment'), 'list-results');
    }

    public function store(EpicRequest $request): RedirectResponse
    {
        $deletedEpic = Epic::onlyTrashed()
            ->where('project_id', $request->integer('project_id'))
            ->where('name', $request->string('name')->toString())
            ->latest('deleted_at')
            ->first();

        if ($deletedEpic && ! $request->boolean('reuse_deleted_name')) {
            return to_route('epics.index')
                ->withInput()
                ->with('deleted_epic_conflict', [
                    'id' => $deletedEpic->id,
                    'name' => $deletedEpic->name,
                ]);
        }

        try {
            Epic::create($request->validated());
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception);
        }

        return to_route('epics.index')->with('status', __('Epic created successfully.'));
    }

    public function update(EpicRequest $request, Epic $epic): RedirectResponse
    {
        try {
            $epic->update($request->validated());
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception);
        }

        return to_route('epics.index')->with('status', __('Epic updated successfully.'));
    }

    public function destroy(Epic $epic): RedirectResponse
    {
        $this->authorize('delete', $epic);
        $epic->delete();

        return to_route('epics.index')->with('status', __('Epic moved to trash.'));
    }
}
