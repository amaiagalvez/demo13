<?php

namespace App\Queries\Projects;

use App\Models\Project;
use App\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * @extends ListQueryBase<Project>
 */
final class ProjectListQuery extends ListQueryBase
{
    private const SEARCH_COLUMNS = [
        'projects.name',
        'project_customers.name',
        'projects.start_date',
        'projects.end_date',
    ];

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    public function active(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withActiveCounts($this->withCustomer(Project::query()->where('projects.active', true)))
                ->withExists(['epics' => fn (Builder $query) => $query->withoutGlobalScope(SoftDeletingScope::class)])
                ->orderBy('projects.start_date')
                ->orderBy('projects.end_date')
                ->orderBy('project_customers.name')
                ->orderBy('projects.name')
                ->orderBy('projects.id'),
            $search,
            searchColumns: self::SEARCH_COLUMNS,
        );
    }

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    public function inactive(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withActiveCounts($this->withCustomer(Project::query()->where('projects.active', false)))
                ->orderBy('projects.start_date')
                ->orderBy('projects.end_date')
                ->orderBy('project_customers.name')
                ->orderBy('projects.name')
                ->orderBy('projects.id'),
            $search,
            searchColumns: [...self::SEARCH_COLUMNS, 'projects.updated_at'],
        );
    }

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    public function trashed(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withActiveCounts($this->withCustomer(Project::onlyTrashed()))
                ->latest('projects.deleted_at')
                ->orderBy('projects.id'),
            $search,
            searchColumns: [...self::SEARCH_COLUMNS, 'projects.deleted_at'],
        );
    }

    public function findTrashedByName(string $name): ?Project
    {
        return $this->firstTrashedByName(Project::onlyTrashed(), $name);
    }

    /**
     * Number of records in each list state, shown as the badge of the state tabs.
     *
     * @return array{active: int, inactive: int, trashed: int}
     */
    public function stateCounts(): array
    {
        return [
            'active' => Project::query()->where('projects.active', true)->count(),
            'inactive' => Project::query()->where('projects.active', false)->count(),
            'trashed' => Project::onlyTrashed()->count(),
        ];
    }

    /**
     * Only active epics and the comments written on them are counted; deleted ones are excluded by
     * the relations.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    private function withActiveCounts(Builder $query): Builder
    {
        return $query->withCount([
            'epics' => fn (Builder $epics) => $epics->where('epics.active', true),
            'comments' => fn (Builder $comments) => $comments->where('epics.active', true),
        ]);
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    private function withCustomer(Builder $query): Builder
    {
        return $query
            ->with('customer')
            ->join('customers as project_customers', 'project_customers.id', '=', 'projects.customer_id')
            ->select('projects.*');
    }
}
