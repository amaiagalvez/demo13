<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use Illuminate\View\View;
use App\Queries\Epics\EpicListQuery;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\EpicListRequest;
use Illuminate\Database\Eloquent\Model;
use App\Http\Requests\EpicDestroyRequest;
use App\Http\Requests\EpicRestoreRequest;
use App\Transformers\EpicListTransformer;

/**
 * @extends TrashController<Epic>
 */
class EpicTrashController extends TrashController
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
        return $this->restoreTrashed($request);
    }

    public function destroy(EpicDestroyRequest $request): RedirectResponse
    {
        return $this->destroyTrashed($request);
    }

    /**
     * @param  Epic  $record
     */
    protected function restoreTrashedRecord(Model $record): void
    {
        $record->restore();
    }

    /**
     * Epic names are only unique inside their project, so the search is narrowed to it.
     *
     * @param  Epic  $record
     */
    protected function nameIsTaken(Model $record): bool
    {
        return $this->takenBy(
            Epic::query()->where('project_id', $record->project_id),
            $record->name,
        );
    }

    protected function recordClass(): string
    {
        return Epic::class;
    }

    protected function trashRoute(): string
    {
        return 'epics.trash.index';
    }
}
