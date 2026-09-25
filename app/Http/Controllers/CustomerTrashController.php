<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerListRequest;
use App\Models\Customer;
use App\Queries\Customers\CustomerListQuery;
use App\Transformers\CustomerListTransformer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerTrashController extends Controller
{
    public function index(
        CustomerListRequest $request,
        CustomerListQuery $query,
        CustomerListTransformer $transformer,
    ): View {
        $search = $request->search();
        $customers = $query->trashed($search);

        return view('customers.list', [
            'customers' => $customers,
            'list' => $transformer->trash($customers, $search),
        ]);
    }

    public function restore(Request $request, int $customer): RedirectResponse
    {
        $customer = Customer::onlyTrashed()->findOrFail($customer);
        $this->authorize('restore', $customer);
        $customer->restore();

        $message = $request->boolean('resolve_name_conflict')
            ? __('Customer restored successfully. No new customer was created with the repeated name.')
            : __('Customer restored successfully.');

        return to_route('customers.trash.index')->with('status', $message);
    }

    public function destroy(int $customer): RedirectResponse
    {
        $customer = Customer::onlyTrashed()->findOrFail($customer);
        $this->authorize('forceDelete', $customer);
        $customer->forceDelete();

        return to_route('customers.trash.index')->with('status', __('Customer permanently deleted.'));
    }
}
