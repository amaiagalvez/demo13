<?php

namespace App\Queries\Epics;

use App\Models\Epic;
use App\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends ListQueryBase<Epic>
 */
final class EpicListQuery extends ListQueryBase
{
    /**
     * Only the most recent comments are embedded in each list row; the total is in comments_count.
     */
    public const RECENT_COMMENTS_LIMIT = 20;

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
            $this->withProjectAndCustomer(Epic::query()->where('epics.active', true))
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
    public function inactive(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withProjectAndCustomer(Epic::query()->where('epics.active', false))
                ->orderByRaw('epics.start_date IS NULL')
                ->orderBy('epics.start_date')
                ->orderByRaw('epics.end_date IS NULL')
                ->orderBy('epics.end_date')
                ->orderBy('epic_projects.name')
                ->orderBy('epic_customers.name')
                ->orderBy('epics.name')
                ->orderBy('epics.id'),
            $search,
            searchColumns: [...self::SEARCH_COLUMNS, 'epics.updated_at'],
        );
    }

    /**
     * @return LengthAwarePaginator<int, Epic>
     */
    public function trashed(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withProjectAndCustomer(Epic::onlyTrashed())
                ->latest('epics.deleted_at')
                ->orderBy('epics.id'),
            $search,
            searchColumns: [...self::SEARCH_COLUMNS, 'epics.deleted_at'],
        );
    }

    public function findTrashedByName(string $name, int $projectId): ?Epic
    {
        $query = Epic::onlyTrashed()->where('project_id', $projectId);

        return $this->firstTrashedByName($query, $name);
    }

    /**
     * Number of records in each list state, shown as the badge of the state tabs.
     *
     * @return array{active: int, inactive: int, trashed: int}
     */
    public function stateCounts(): array
    {
        return [
            'active' => Epic::query()->where('epics.active', true)->count(),
            'inactive' => Epic::query()->where('epics.active', false)->count(),
            'trashed' => Epic::onlyTrashed()->count(),
        ];
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
