<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerListRequest;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Queries\Customers\CustomerListQuery;
use App\Transformers\CustomerListTransformer;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(
        CustomerListRequest $request,
        CustomerListQuery $query,
        CustomerListTransformer $transformer,
    ): View {
        $search = $request->search();
        $customers = $query->active($search);

        return view('customers.list', [
            'customers' => $customers,
            'list' => $transformer->active($customers, $search),
        ]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $name = $request->string('name')->toString();
        $deletedCustomer = Customer::onlyTrashed()
            ->where('name', $name)
            ->latest('deleted_at')
            ->first();

        if ($deletedCustomer && ! $request->boolean('reuse_deleted_name')) {
            return to_route('customers.index')
                ->withInput()
                ->with('deleted_customer_conflict', [
                    'id' => $deletedCustomer->id,
                    'name' => $deletedCustomer->name,
                ]);
        }

        Customer::create($request->validated());

        return to_route('customers.index')->with('status', __('Customer created successfully.'));
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return to_route('customers.index')->with('status', __('Customer updated successfully.'));
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        return to_route('customers.index')->with('status', __('Customer moved to trash.'));
    }
}
