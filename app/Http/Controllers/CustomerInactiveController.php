<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\CustomerListRequest;
use App\Queries\Customers\CustomerListQuery;
use App\Transformers\CustomerListTransformer;

class CustomerInactiveController extends Controller
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
        $this->authorize('deactivate', $customer);
        $customer->active = false;
        $customer->save();

        return to_route('customers.index')->with('status', __('Record deactivated successfully.'));
    }

    public function reactivate(Customer $customer): RedirectResponse
    {
        $this->authorize('reactivate', $customer);
        $customer->active = true;
        $customer->save();

        return to_route('customers.inactive.index')->with('status', __('Record reactivated successfully.'));
    }
}
