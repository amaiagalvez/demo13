<?php

namespace App\Scratch;

use App\Models\Epic;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @template TRestoreable of Model
 */
abstract class Probe
{
    /**
     * @return class-string<TRestoreable>
     */
    abstract protected function recordClass(): string;

    /**
     * @return TRestoreable
     */
    abstract protected function trashedRecord(int $key): Model;

    protected function restore(int $key): bool
    {
        /** @var Model&SoftDeletes $record */
        $record = $this->trashedRecord($key);
        $record->restore();

        return true;
    }

    protected function forceDelete(int $key): bool
    {
        $recordClass = $this->recordClass();
        /** @var Model&SoftDeletes $record */
        $record = $recordClass::onlyTrashed()->whereKey($key)->lockForUpdate()->firstOrFail();

        return $record->forceDelete() !== false;
    }

    private function timestamp(Model $record, string $column): ?string
    {
        $value = $record->getAttribute($column);

        return $value instanceof Carbon ? $value->toIso8601String() : null;
    }

    private function flag(Model $record, string $column): bool
    {
        return (bool) $record->getAttribute($column);
    }

    private function text(Model $record, string $column): string
    {
        $value = $record->getAttribute($column);

        return is_string($value) ? $value : '';
    }

    protected function extraDate(Model $record): ?string
    {
        return $this->timestamp($record, 'deleted_at');
    }

    protected function blocked(Model $record): bool
    {
        return $this->flag($record, 'epics_exists');
    }

    protected function nameOf(Model $record): string
    {
        return $this->text($record, 'name');
    }

    protected function check(): void
    {
        /** @var Epic $epic */
        $epic = Epic::onlyTrashed()->findOrFail(1);
        $epic->restore();
    }
}