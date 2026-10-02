<?php

namespace App\Queries\Customers;

use App\Models\Customer;
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
            Customer::query()->where('active', true)
                ->withExists(['projects' => fn (Builder $query) => $query->withoutGlobalScope(SoftDeletingScope::class)])
                ->orderBy('name'),
            $search,
            searchColumns: ['name', 'created_at'],
        );
    }

    /**
     * @return LengthAwarePaginator<int, Customer>
     */
    public function inactive(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            Customer::query()->where('active', false)->orderBy('name')->orderBy('id'),
            $search,
            searchColumns: ['name', 'created_at', 'updated_at'],
        );
    }

    /**
     * @return LengthAwarePaginator<int, Customer>
     */
    public function trashed(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            Customer::onlyTrashed()->latest('deleted_at')->orderBy('id'),
            $search,
            searchColumns: ['name', 'deleted_at'],
        );
    }
}
