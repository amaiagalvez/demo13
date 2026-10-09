<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Http\Requests\RestoreRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Requests\TrashDestroyRequest;
use App\Support\Database\UniqueConstraintViolation;

/**
 * Restores and permanently deletes a trashed record.
 *
 * The two verbs lean on different guarantees, so they are documented apart:
 *
 * - The restore takes no lock. What stops a name inserted mid-request from slipping past is the
 *   `active_name` unique index: restoring clears `deleted_at`, which makes that generated column
 *   non-null and therefore index-checked, and the collision arrives as the QueryException that
 *   restoreTrashed() turns into the conflict response. The nameIsTaken() pre-check is only a fast
 *   path. Do not drop the catch on the strength of the pre-check.
 * - The permanent delete does take a row lock, on the record it is deleting, so it cannot race a
 *   concurrent force delete of the same row. That lock says nothing about name uniqueness.
 *
 * Each controller keeps the concrete requests in its own signatures because Laravel builds them out
 * of those type hints, and hands them over to restoreTrashed() and destroyTrashed() here.
 *
 * @template TRestoreable of Model
 */
abstract class TrashController extends Controller
{
    /**
     * @param  RestoreRequest<TRestoreable>  $request
     */
    final protected function restoreTrashed(RestoreRequest $request): RedirectResponse
    {
        $record = $request->record();

        if ($this->nameIsTaken($record)) {
            return $this->restoreConflictResponse();
        }

        try {
            $this->restoreTrashedRecord($record);
        } catch (QueryException $exception) {
            if (! UniqueConstraintViolation::causedBy($exception)) {
                throw $exception;
            }

            return $this->restoreConflictResponse();
        }

        $message = $request->boolean('resolve_name_conflict')
            ? __('basics13::messages.restored_no_new_record')
            : __('basics13::messages.restored');

        // save() reports true whether it updated a row or not, so a restore whose row was
        // permanently deleted by a concurrent request would otherwise claim a success it did not
        // achieve. Re-reading the key is what tells the two apart.
        if (! $this->restoreTookEffect($record)) {
            return $this->restoreMissingResponse();
        }

        return to_route($this->trashRoute())->with('status', $message);
    }

    /**
     * Whether the record is present and out of the trash after the restore.
     */
    private function restoreTookEffect(Model $record): bool
    {
        $recordClass = $this->recordClass();

        return $recordClass::withTrashed()
            ->whereKey($record->getKey())
            ->whereNull('deleted_at')
            ->exists();
    }

    private function restoreMissingResponse(): RedirectResponse
    {
        return to_route($this->trashRoute())
            ->with('error', __('basics13::messages.not_in_trash'));
    }

    /**
     * @param  TrashDestroyRequest<TRestoreable>  $request
     */
    final protected function destroyTrashed(TrashDestroyRequest $request): RedirectResponse
    {
        $deleted = DB::transaction(fn (): bool => $this->forceDeleteLocked($request));

        if (! $deleted) {
            return to_route($this->trashRoute())
                ->with('error', __('Cannot be permanently deleted while it has related records.'));
        }

        return to_route($this->trashRoute())->with('status', __('basics13::messages.permanently_deleted'));
    }

    /**
     * Whether an active record already uses this name, which makes restoring it impossible.
     *
     * @param  Builder<TRestoreable>  $query
     */
    final protected function takenBy(Builder $query, string $name): bool
    {
        return $query->where('name', $name)->exists();
    }

    /**
     * Delete the record for good while holding its row lock.
     *
     * @param  TrashDestroyRequest<TRestoreable>  $request
     */
    private function forceDeleteLocked(TrashDestroyRequest $request): bool
    {
        $recordClass = $this->recordClass();

        $locked = $recordClass::onlyTrashed()
            ->whereKey($request->record()->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        return $locked->forceDelete() !== false;
    }

    private function restoreConflictResponse(): RedirectResponse
    {
        return to_route($this->trashRoute())
            ->with('error', __('basics13::messages.cannot_restore_name_taken'));
    }

    /**
     * Puts the trashed record back among the active ones.
     *
     * @param  TRestoreable  $record
     */
    abstract protected function restoreTrashedRecord(Model $record): void;

    /**
     * Whether an active record already uses the name of this trashed one.
     *
     * @param  TRestoreable  $record
     */
    abstract protected function nameIsTaken(Model $record): bool;

    /**
     * Soft deletable model class the trash routes of this resource address.
     */
    abstract protected function recordClass(): string;

    /**
     * Route the user lands on after any trash action.
     */
    abstract protected function trashRoute(): string;
}
