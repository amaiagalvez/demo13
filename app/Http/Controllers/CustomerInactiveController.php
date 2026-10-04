<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\CustomerListRequest;
use App\Queries\Customers\CustomerListQuery;
use App\Transformers\CustomerListTransformer;

class CustomerInactiveController extends InactiveController
{
    public function index(
        CustomerListRequest $request,
        CustomerListQuery $query,
        CustomerListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $customers = $query->inactive($search);

        return $this->listView($request, 'customers.list', [
            'customers' => $customers,
            'list' => $transformer->inactive(
                $customers,
                $search,
                $query->stateCounts(inactiveTotal: $search === '' ? $customers->total() : null),
            ),
        ]);
    }

    public function deactivate(Customer $customer): RedirectResponse
    {
        return $this->deactivateRecord($customer);
    }

    public function reactivate(Customer $customer): RedirectResponse
    {
        return $this->reactivateRecord($customer);
    }

    protected function activeRoute(): string
    {
        return 'customers.index';
    }

    protected function inactiveRoute(): string
    {
        return 'customers.inactive.index';
    }
}
