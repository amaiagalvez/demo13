<?php

namespace App\Queries\Customers;

use App\Models\Customer;
use App\Queries\ListQueryBase;
use Illuminate\Pagination\LengthAwarePaginator;

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
            Customer::query()->orderBy('name'),
            $search,
            searchColumns: ['name', 'created_at'],
        );
    }

    /**
     * @return LengthAwarePaginator<int, Customer>
     */
    public function trashed(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            Customer::onlyTrashed()->latest('deleted_at'),
            $search,
            searchColumns: ['name', 'deleted_at'],
        );
    }
}
