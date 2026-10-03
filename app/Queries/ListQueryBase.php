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
    public const PER_PAGE = 25;

    /**
     * Escape character declared in every LIKE pattern, so a search for `%`, `_` or a
     * backslash behaves the same on every engine and does not depend on the server's
     * implicit escape character or on its sql_mode.
     */
    protected const LIKE_ESCAPE = '!';

    /**
     * @param  Builder<TModel>  $query
     * @param  non-empty-list<literal-string>  $searchColumns
     * @return LengthAwarePaginator<int, TModel>
     */
    protected function paginate(
        Builder $query,
        string $search,
        array $searchColumns,
    ): LengthAwarePaginator {
        if ($search !== '') {
            $pattern = $this->searchPattern($search);
            $clause = ' like ? escape \''.self::LIKE_ESCAPE.'\'';

            $query->where(function (Builder $query) use ($pattern, $clause, $searchColumns): void {
                foreach ($searchColumns as $column) {
                    $query->orWhereRaw($column.$clause, [$pattern]);
                }
            });
        }

        $items = $query->paginate(static::PER_PAGE);

        if ($search !== '') {
            $items->appends(['search' => $search]);
        }

        return $items;
    }

    /**
     * @param  Builder<TModel>  $query
     * @return TModel|null
     */
    protected function firstTrashedByName(Builder $query, string $name): ?Model
    {
        return $query->where('name', $name)->latest('deleted_at')->first();
    }

    /**
     * Escape the LIKE wildcards and the escape character itself in a search term.
     */
    protected function searchPattern(string $search): string
    {
        return '%'.strtr($search, [
            '%' => self::LIKE_ESCAPE.'%',
            '_' => self::LIKE_ESCAPE.'_',
            self::LIKE_ESCAPE => self::LIKE_ESCAPE.self::LIKE_ESCAPE,
        ]).'%';
    }
}
