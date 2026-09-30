<?php

namespace App\Queries\Projects;

use App\Models\Project;
use App\Queries\ListQueryBase;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends ListQueryBase<Project>
 */
final class ProjectListQuery extends ListQueryBase
{
    /**
     * @return LengthAwarePaginator<int, Project>
     */
    public function active(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            Project::query()
                ->with('customer')
                ->join('customers as project_customers', 'project_customers.id', '=', 'projects.customer_id')
                ->select('projects.*')
                ->orderBy('projects.start_date')
                ->orderBy('projects.end_date')
                ->orderBy('project_customers.name'),
            $search,
            searchColumns: [
                'projects.name',
                'project_customers.name',
                'projects.start_date',
                'projects.end_date',
                'projects.created_at',
            ],
        );
    }

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    public function trashed(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            Project::onlyTrashed()
                ->with('customer')
                ->join('customers as project_customers', 'project_customers.id', '=', 'projects.customer_id')
                ->select('projects.*')
                ->latest('projects.deleted_at'),
            $search,
            searchColumns: [
                'projects.name',
                'project_customers.name',
                'projects.start_date',
                'projects.end_date',
                'projects.deleted_at',
            ],
        );
    }
}
