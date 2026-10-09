<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use Illuminate\View\View;
use App\Queries\Epics\EpicListQuery;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\EpicListRequest;
use App\Transformers\EpicListTransformer;
use Basics13\Http\Controllers\ArchivedController;

class EpicArchivedController extends ArchivedController
{
    public function index(
        EpicListRequest $request,
        EpicListQuery $query,
        EpicListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $epics = $query->archived($search);

        return $this->listView($request, 'epics.list', [
            'epics' => $epics,
            'list' => $transformer->archived(
                $epics,
                $search,
                $query->stateCounts(archivedTotal: $search === '' ? $epics->total() : null),
            ),
        ]);
    }

    public function archive(Epic $epic): RedirectResponse
    {
        return $this->archiveRecord($epic);
    }

    public function activate(Epic $epic): RedirectResponse
    {
        return $this->activateRecord($epic);
    }

    protected function activeRoute(): string
    {
        return 'epics.index';
    }

    protected function archivedRoute(): string
    {
        return 'epics.archived.index';
    }
}
