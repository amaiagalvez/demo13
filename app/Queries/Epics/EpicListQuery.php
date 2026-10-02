<?php

namespace App\Queries\Epics;

use App\Models\Epic;
use App\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @extends ListQueryBase<Epic>
 */
final class EpicListQuery extends ListQueryBase
{
    private const SEARCH_COLUMNS = [
        'epics.name',
        'epic_projects.name',
        'epic_customers.name',
        'epics.start_date',
        'epics.end_date',
    ];

    /**
     * Epics without dates are listed after dated ones.
     *
     * @return LengthAwarePaginator<int, Epic>
     */
    public function active(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withProjectAndCustomer(Epic::query())
                ->with(['comments' => fn(HasMany $query) => $query->with('user')->latest()->latest('id')])
                ->orderByRaw('epics.start_date IS NULL')
                ->orderBy('epics.start_date')
                ->orderByRaw('epics.end_date IS NULL')
                ->orderBy('epics.end_date')
                ->orderBy('epic_projects.name')
                ->orderBy('epic_customers.name')
                ->orderBy('epics.name')
                ->orderBy('epics.id'),
            $search,
            searchColumns: self::SEARCH_COLUMNS,
        );
    }

    /**
     * @return LengthAwarePaginator<int, Epic>
     */
    public function trashed(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withProjectAndCustomer(Epic::onlyTrashed())
                ->latest('epics.deleted_at'),
            $search,
            searchColumns: [...self::SEARCH_COLUMNS, 'epics.deleted_at'],
        );
    }

    /**
     * @param  Builder<Epic>  $query
     * @return Builder<Epic>
     */
    private function withProjectAndCustomer(Builder $query): Builder
    {
        return $query
            ->with('project.customer')
            ->join('projects as epic_projects', 'epic_projects.id', '=', 'epics.project_id')
            ->join('customers as epic_customers', 'epic_customers.id', '=', 'epic_projects.customer_id')
            ->select('epics.*')
            ->withCount('comments');
    }
}
