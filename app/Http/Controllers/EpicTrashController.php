<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use Illuminate\View\View;
use Illuminate\Http\Request;
use App\Queries\Epics\EpicListQuery;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\EpicListRequest;
use Illuminate\Database\QueryException;
use App\Transformers\EpicListTransformer;
use App\Support\Database\UniqueConstraintViolation;

class EpicTrashController extends Controller
{
    public function index(
        EpicListRequest $request,
        EpicListQuery $query,
        EpicListTransformer $transformer,
    ): View {
        $search = $request->search();
        $epics = $query->trashed($search);

        return view('epics.list', [
            'epics' => $epics,
            'availableProjects' => collect(),
            'list' => $transformer->trash($epics, $search),
        ]);
    }

    public function restore(Request $request, int $epic): RedirectResponse
    {
        $epic = Epic::onlyTrashed()->findOrFail($epic);
        $this->authorize('restore', $epic);

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
            ? __('Epic restored successfully. No new epic was created with the repeated name.')
            : __('Epic restored successfully.');

        return to_route('epics.trash.index')->with('status', $message);
    }

    public function destroy(int $epic): RedirectResponse
    {
        $epic = Epic::onlyTrashed()->findOrFail($epic);
        $this->authorize('forceDelete', $epic);
        $epic->forceDelete();

        return to_route('epics.trash.index')->with('status', __('Epic permanently deleted.'));
    }

    private function restoreConflictResponse(): RedirectResponse
    {
        return to_route('epics.trash.index')
            ->with('error', __('Epic cannot be restored while another active epic in the same project uses this name.'));
    }
}
