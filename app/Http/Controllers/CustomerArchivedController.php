<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\CustomerListRequest;
use App\Queries\Customers\CustomerListQuery;
use App\Transformers\CustomerListTransformer;

class CustomerArchivedController extends ArchivedController
{
    public function index(
        CustomerListRequest $request,
        CustomerListQuery $query,
        CustomerListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $customers = $query->archived($search);

        return $this->listView($request, 'customers.list', [
            'customers' => $customers,
            'list' => $transformer->archived(
                $customers,
                $search,
                $query->stateCounts(archivedTotal: $search === '' ? $customers->total() : null),
            ),
        ]);
    }

    public function archive(Customer $customer): RedirectResponse
    {
        return $this->archiveRecord($customer);
    }

    public function activate(Customer $customer): RedirectResponse
    {
        return $this->activateRecord($customer);
    }

    protected function activeRoute(): string
    {
        return 'customers.index';
    }

    protected function archivedRoute(): string
    {
        return 'customers.archived.index';
    }
}
