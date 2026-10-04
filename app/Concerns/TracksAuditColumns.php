<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Keeps the `created_by` / `updated_by` / `deleted_by` columns of a table in step with every write
 * made through the model: creating stamps the first two, any change stamps `updated_by`, trashing
 * stamps `deleted_by` and restoring clears it.
 *
 * The actor is whoever the guard resolves, and a column is only written when there is one: a console
 * command, a queued job or an unauthenticated flow leaves the trail of whoever really created or
 * changed the row instead of erasing it.
 *
 * The columns come from `addAuditColumns()` in `app/Support/Database/helpers.php` and are never
 * mass assignable, so a request payload cannot claim authorship.
 */
trait TracksAuditColumns
{
    public static function bootTracksAuditColumns(): void
    {
        static::creating(static function (self $model): void {
            $model->stampAuditColumn('created_by');
            $model->stampAuditColumn('updated_by');
        });

        static::updating(static function (self $model): void {
            $model->stampAuditColumn('updated_by');
        });

        // A force delete leaves no row behind to attribute the deletion to, and a model without
        // SoftDeletes has no trashed state at all, so neither of them has a deleted_by to write.
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::registerModelEvent('trashed', static function (self $model): void {
                $model->stampAuditColumn('deleted_by');
                $model->stampAuditColumn('updated_by');

                $model->writeStampedAuditColumns();
            });

            static::registerModelEvent('restoring', static function (self $model): void {
                $model->setAttribute('deleted_by', null);
            });
        }
    }

    /**
     * Write the current user into an audit column, leaving it as it is when there is none.
     */
    protected function stampAuditColumn(string $column): void
    {
        if (($actor = $this->auditActorId()) !== null) {
            $this->setAttribute($column, $actor);
        }
    }

    /**
     * Persist the audit columns stamped on this instance by a soft delete.
     *
     * SoftDeletes::runSoftDelete() updates deleted_at and updated_at and nothing else, so what the
     * `trashed` hook stamped is still unsaved once the row is soft deleted. This writes exactly the
     * stamped columns on that same row, before the caller's delete() returns.
     */
    protected function writeStampedAuditColumns(): void
    {
        $audit = [];

        foreach (['updated_by', 'deleted_by'] as $column) {
            if ($this->isDirty($column)) {
                $audit[$column] = $this->getAttribute($column);
            }
        }

        if ($audit === []) {
            return;
        }

        $this->setKeysForSaveQuery($this->newModelQuery())->update($audit);

        $this->syncOriginalAttributes(array_keys($audit));
    }

    /**
     * The id behind the current request, or null when there is no user to attribute the write to.
     */
    protected function auditActorId(): ?int
    {
        $actor = Auth::id();

        return $actor === null ? null : (int) $actor;
    }
}
