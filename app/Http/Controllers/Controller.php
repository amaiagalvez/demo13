<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Render a list view, or only its results fragment when the list is being refreshed.
     *
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     */
    protected function listView(Request $request, string $view, array $data): View|string
    {
        $view = view($view, $data);

        return $request->hasHeader('X-List-Fragment')
            ? $view->fragment('list-results')
            : $view;
    }
}
