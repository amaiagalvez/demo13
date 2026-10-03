<?php

namespace App\Queries\Customers;

use App\Models\Customer;
use App\Models\EpicComment;
use App\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * @extends ListQueryBase<Customer>
 */
final class CustomerListQuery extends ListQueryBase
{
    /**
     * @return LengthAwarePaginator<int, Customer>
     */
    public function active(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withActiveCounts(
                Customer::query()->where('active', true)
                    ->withExists(['projects' => fn (Builder $query) => $query->withoutGlobalScope(SoftDeletingScope::class)])
                    ->orderBy('name'),
            ),
            $search,
            searchColumns: ['name'],
        );
    }

    /**
     * @return LengthAwarePaginator<int, Customer>
     */
    public function inactive(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withActiveCounts(Customer::query()->where('active', false)->orderBy('name')->orderBy('id')),
            $search,
            searchColumns: ['name', 'updated_at'],
        );
    }

    /**
     * @return LengthAwarePaginator<int, Customer>
     */
    public function trashed(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withActiveCounts(Customer::onlyTrashed()->latest('deleted_at')->orderBy('id')),
            $search,
            searchColumns: ['name', 'deleted_at'],
        );
    }

    /**
     * Only the live slice of the customer is counted: active projects, the active epics they own and
     * the comments written on those epics; deleted records are excluded by the joins below.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    private function withActiveCounts(Builder $query): Builder
    {
        return $query
            ->select('customers.*')
            ->withCount([
                'projects' => fn (Builder $projects) => $projects->where('projects.active', true),
                'epics' => fn (Builder $epics) => $epics
                    ->where('epics.active', true)
                    ->where('projects.active', true),
            ])
            ->selectSub($this->activeCommentsCount(), 'comments_count');
    }

    /**
     * Comments live two hops away from the customer, which `withCount` cannot express, so the
     * correlated subquery walks projects and epics explicitly.
     *
     * @return Builder<EpicComment>
     */
    private function activeCommentsCount(): Builder
    {
        return EpicComment::query()
            ->selectRaw('count(*)')
            ->join('epics', 'epics.id', '=', 'epic_comments.epic_id')
            ->join('projects', 'projects.id', '=', 'epics.project_id')
            ->whereColumn('projects.customer_id', 'customers.id')
            ->where('projects.active', true)
            ->whereNull('projects.deleted_at')
            ->where('epics.active', true)
            ->whereNull('epics.deleted_at');
    }
}
