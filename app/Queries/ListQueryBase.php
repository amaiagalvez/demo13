<?php

namespace App\Queries;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @template TModel of Model
 */
abstract class ListQueryBase
{
    public const PER_PAGE = 5;

    /**
     * @param  Builder<TModel>  $query
     * @param  non-empty-list<string>  $searchColumns
     * @return LengthAwarePaginator<int, TModel>
     */
    protected function paginate(
        Builder $query,
        string $search,
        array $searchColumns,
    ): LengthAwarePaginator {
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search, $searchColumns): void {
                $query->where($searchColumns[0], 'like', "%{$search}%");

                foreach (array_slice($searchColumns, 1) as $column) {
                    $query->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        $items = $query->paginate(static::PER_PAGE);

        if ($search !== '') {
            $items->appends(['search' => $search]);
        }

        return $items;
    }
}
