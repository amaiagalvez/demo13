<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\CustomerRequest;
use Illuminate\Database\QueryException;
use App\Http\Requests\CustomerListRequest;
use App\Queries\Customers\CustomerListQuery;
use App\Transformers\CustomerListTransformer;
use App\Http\Requests\CustomerSelectOptionsRequest;
use App\Support\Database\UniqueConstraintViolation;
use App\Queries\Customers\CustomerSelectOptionsQuery;

class CustomerController extends Controller
{
    public function index(
        CustomerListRequest $request,
        CustomerListQuery $query,
        CustomerListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $customers = $query->active($search);

        return $this->listView($request, 'customers.list', [
            'customers' => $customers,
            'list' => $transformer->active($customers, $search),
        ]);
    }

    public function selectOptions(
        CustomerSelectOptionsRequest $request,
        CustomerSelectOptionsQuery $query,
    ): JsonResponse {
        return response()->json([
            'results' => $query->search($request->search()),
        ]);
    }

    public function store(CustomerRequest $request): RedirectResponse|JsonResponse
    {
        $name = $request->string('name')->toString();
        $deletedCustomer = Customer::onlyTrashed()
            ->where('name', $name)
            ->latest('deleted_at')
            ->first();

        if ($deletedCustomer && ! $request->boolean('reuse_deleted_name')) {
            if ($request->expectsJson()) {
                $message = __('A deleted customer already uses the name :name.', ['name' => $deletedCustomer->name]);

                return response()->json([
                    'message' => $message,
                    'errors' => ['name' => [$message]],
                ], 409);
            }

            return to_route('customers.index')
                ->withInput()
                ->with('deleted_customer_conflict', [
                    'id' => $deletedCustomer->id,
                    'name' => $deletedCustomer->name,
                ]);
        }

        try {
            $customer = Customer::create($request->validated());
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception);
        }

        if ($request->expectsJson()) {
            return response()->json($customer->only(['id', 'name']), 201);
        }

        return to_route('customers.index')->with('status', __('Customer created successfully.'));
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        try {
            $customer->update($request->validated());
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception);
        }

        return to_route('customers.index')->with('status', __('Customer updated successfully.'));
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        if ($customer->projects()->withTrashed()->exists()) {
            return to_route('customers.index')
                ->with('error', __('Customer cannot be deleted while it has projects.'));
        }

        $customer->delete();

        return to_route('customers.index')->with('status', __('Customer moved to trash.'));
    }
}
