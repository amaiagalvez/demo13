<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use App\Queries\Timeline\TimelineQuery;
use Basics13\Http\Controllers\Controller;
use App\Http\Requests\TimelineListRequest;

class TimelineController extends Controller
{
    /**
     * The active projects of one page with their active epics nested underneath, ready to be
     * drawn as bars on a single month axis. `?search=` narrows both levels: a project that
     * matches brings its epics along, a project that does not match only brings the epics that do.
     */
    public function index(TimelineListRequest $request, TimelineQuery $query): View
    {
        $search = $request->search();

        return view('timeline.index', [
            'search' => [
                'action' => route('timeline'),
                'value' => $search,
                'placeholder' => __('Search projects and epics'),
            ],
            'timeline' => $query->overview($search),
        ]);
    }
}
