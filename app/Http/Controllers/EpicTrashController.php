<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use Illuminate\View\View;
use App\Queries\Epics\EpicListQuery;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\EpicListRequest;
use Illuminate\Database\QueryException;
use App\Http\Requests\EpicRestoreRequest;
use App\Transformers\EpicListTransformer;
use App\Support\Database\UniqueConstraintViolation;

class EpicTrashController extends Controller
{
    public function index(
        EpicListRequest $request,
        EpicListQuery $query,
        EpicListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $epics = $query->trashed($search);

        return $this->listView($request, 'epics.list', [
            'epics' => $epics,
            'hasProjects' => false,
            'selectedProjectOption' => null,
            'list' => $transformer->trash(
                $epics,
                $search,
                $query->stateCounts(trashedTotal: $search === '' ? $epics->total() : null),
            ),
        ]);
    }

    public function restore(EpicRestoreRequest $request): RedirectResponse
    {
        $epic = $request->epic();

        $nameIsInUse = Epic::query()
            ->where('project_id', $epic->project_id)
            ->where('name', $epic->name)
            ->exists();

        if ($nameIsInUse) {
            return $this->restoreConflictResponse();
        }

        try {
            $epic->restore();
        } catch (QueryException $exception) {
            if (! UniqueConstraintViolation::causedBy($exception)) {
                throw $exception;
            }

            return $this->restoreConflictResponse();
        }

        $message = $request->boolean('resolve_name_conflict')
            ? __('Record restored successfully. No new record was created with the repeated name.')
            : __('Record restored successfully.');

        return to_route('epics.trash.index')->with('status', $message);
    }

    public function destroy(int $epic): RedirectResponse
    {
        $epic = Epic::onlyTrashed()->findOrFail($epic);
        $this->authorize('forceDelete', $epic);
        $epic->forceDelete();

        return to_route('epics.trash.index')->with('status', __('Record permanently deleted.'));
    }

    private function restoreConflictResponse(): RedirectResponse
    {
        return to_route('epics.trash.index')
            ->with('error', __('Cannot be restored because another record outside the trash uses this name.'));
    }
}
