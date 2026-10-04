<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use Illuminate\View\View;
use App\Queries\Epics\EpicListQuery;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\EpicListRequest;
use App\Transformers\EpicListTransformer;

class EpicInactiveController extends InactiveController
{
    public function index(
        EpicListRequest $request,
        EpicListQuery $query,
        EpicListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $epics = $query->inactive($search);

        return $this->listView($request, 'epics.list', [
            'epics' => $epics,
            'list' => $transformer->inactive(
                $epics,
                $search,
                $query->stateCounts(inactiveTotal: $search === '' ? $epics->total() : null),
            ),
        ]);
    }

    public function deactivate(Epic $epic): RedirectResponse
    {
        return $this->deactivateRecord($epic);
    }

    public function reactivate(Epic $epic): RedirectResponse
    {
        return $this->reactivateRecord($epic);
    }

    protected function activeRoute(): string
    {
        return 'epics.index';
    }

    protected function inactiveRoute(): string
    {
        return 'epics.inactive.index';
    }
}
