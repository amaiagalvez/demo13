<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
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
            'list' => $transformer->active(
                $customers,
                $search,
                $query->stateCounts(activeTotal: $search === '' ? $customers->total() : null),
            ),
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

    public function store(CustomerRequest $request, CustomerListQuery $query): RedirectResponse|JsonResponse
    {
        $name = $request->string('name')->toString();
        $deletedCustomer = $query->findTrashedByName($name);

        if ($deletedCustomer !== null && ! $request->boolean('reuse_deleted_name')) {
            return $this->deletedNameConflict($request, $deletedCustomer);
        }

        try {
            $customer = Customer::create($request->validated());
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception);
        }

        if ($request->expectsJson()) {
            return response()->json($customer->only(['id', 'name']), 201);
        }

        return to_route('customers.index')->with('status', __('Record created successfully.'));
    }


    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        try {
            $customer->update($request->validated());
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception);
        }

        return to_route('customers.index')->with('status', __('Record updated successfully.'));
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $deleted = DB::transaction(static function () use ($customer): bool {
            $lockedCustomer = Customer::query()
                ->whereKey($customer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            return $lockedCustomer->delete() !== false;
        });

        if (! $deleted) {
            return to_route('customers.index')
                ->with('error', __('Cannot be deleted while it has related records.'));
        }

        return to_route('customers.index')->with('status', __('Record moved to trash.'));
    }
}
