<?php

namespace App\Queries\Customers;

use App\Models\Customer;
use App\Queries\ListQueryBase;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends ListQueryBase<Customer>
 */
final class CustomerSelectOptionsQuery extends ListQueryBase
{
    private const RESULTS_LIMIT = 20;

    /**
     * @return Collection<int, array{id: int<0, max>, text: string}>
     */
    public function search(string $search): Collection
    {
        $pattern = $this->searchPattern($search);

        return Customer::query()
            ->where('active', true)
            ->when($search !== '', fn(Builder $query) => $query->whereRaw(
                'customers.name LIKE ? ESCAPE \'' . self::LIKE_ESCAPE . '\'',
                [$pattern],
            ))
            ->orderBy('customers.name')
            ->orderBy('customers.id')
            ->limit(self::RESULTS_LIMIT)
            ->get(['id', 'name'])
            ->map(fn(Customer $customer): array => [
                'id' => $customer->id,
                'text' => $customer->name,
            ]);
    }
}
