<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Model;

/**
 * Deactivates and reactivates a record.
 *
 * Both actions are the same for every resource, so each controller only names the routes it
 * returns to. The signatures keep the concrete model because Laravel binds the route parameter to
 * the type hint.
 */
abstract class InactiveController extends Controller
{
    final protected function deactivateRecord(Model $record): RedirectResponse
    {
        $this->authorize('deactivate', $record);

        $record->setAttribute('active', false);
        $record->save();

        return to_route($this->activeRoute())->with('status', __('Record deactivated successfully.'));
    }

    final protected function reactivateRecord(Model $record): RedirectResponse
    {
        $this->authorize('reactivate', $record);

        $record->setAttribute('active', true);
        $record->save();

        return to_route($this->inactiveRoute())->with('status', __('Record reactivated successfully.'));
    }

    /**
     * Route the user lands on after a record becomes active again.
     */
    abstract protected function activeRoute(): string;

    /**
     * Route the user lands on after a record is deactivated.
     */
    abstract protected function inactiveRoute(): string;
}
