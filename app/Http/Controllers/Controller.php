<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Model;
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

    /**
     * A record in the trash already uses the name the user is trying to create. The browser is sent
     * back to its list with the conflict modal open; an XHR caller (the inline customer creation of
     * the project selector) gets the conflict as a 409 it can render on the field.
     *
     * @param  Model  $trashedRecord  the trashed record holding the name
     */
    protected function deletedNameConflict(Request $request, Model $trashedRecord): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            $message = __('A deleted record already uses the name :name.', ['name' => $trashedRecord->name]);

            return response()->json([
                'message' => $message,
                'errors' => ['name' => [$message]],
            ], 409);
        }

        $resource = strtolower(class_basename($trashedRecord));

        return to_route("{$resource}s.index")
            ->withInput()
            ->with("deleted_{$resource}_conflict", [
                'id' => $trashedRecord->getKey(),
                'name' => $trashedRecord->name,
            ]);
    }
}
