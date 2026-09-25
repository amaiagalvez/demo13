<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerListRequest;
use App\Models\Customer;
use App\Queries\Customers\CustomerListQuery;
use App\Transformers\CustomerListTransformer;
use Illuminate\Http\RedirectResponse;
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

    public function restore(int $customer): RedirectResponse
    {
        Customer::onlyTrashed()->findOrFail($customer)->restore();

        return to_route('customers.trash.index')->with('status', __('Customer restored successfully.'));
    }

    public function destroy(int $customer): RedirectResponse
    {
        Customer::onlyTrashed()->findOrFail($customer)->forceDelete();

        return to_route('customers.trash.index')->with('status', __('Customer permanently deleted.'));
    }
}
