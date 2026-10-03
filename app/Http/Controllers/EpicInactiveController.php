<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use Illuminate\View\View;
use App\Queries\Epics\EpicListQuery;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\EpicListRequest;
use App\Transformers\EpicListTransformer;

class EpicInactiveController extends Controller
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
            'hasProjects' => false,
            'selectedProjectOption' => null,
            'list' => $transformer->inactive(
                $epics,
                $search,
                $query->stateCounts(inactiveTotal: $search === '' ? $epics->total() : null),
            ),
        ]);
    }

    public function deactivate(Epic $epic): RedirectResponse
    {
        $this->authorize('deactivate', $epic);
        $epic->active = false;
        $epic->save();

        return to_route('epics.index')->with('status', __('Record deactivated successfully.'));
    }

    public function reactivate(Epic $epic): RedirectResponse
    {
        $this->authorize('reactivate', $epic);
        $epic->active = true;
        $epic->save();

        return to_route('epics.inactive.index')->with('status', __('Record reactivated successfully.'));
    }
}
