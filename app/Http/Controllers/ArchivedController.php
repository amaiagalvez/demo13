<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Model;

/**
 * Archives and activates a record.
 *
 * Both actions are the same for every resource, so each controller only names the routes it
 * returns to. The signatures keep the concrete model because Laravel binds the route parameter to
 * the type hint.
 */
abstract class ArchivedController extends Controller
{
    final protected function archiveRecord(Model $record): RedirectResponse
    {
        $this->authorize('archive', $record);

        $record->setAttribute('active', false);
        $record->save();

        return to_route($this->activeRoute())->with('status', __('basics13::messages.archived'));
    }

    final protected function activateRecord(Model $record): RedirectResponse
    {
        $this->authorize('activate', $record);

        $record->setAttribute('active', true);
        $record->save();

        return to_route($this->archivedRoute())->with('status', __('basics13::messages.activated'));
    }

    /**
     * Route the user lands on after a record becomes active again.
     */
    abstract protected function activeRoute(): string;

    /**
     * Route the user lands on after a record is archived.
     */
    abstract protected function archivedRoute(): string;
}
