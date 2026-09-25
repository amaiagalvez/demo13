<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerListRequest;
use App\Models\Customer;
use App\Queries\Customers\CustomerListQuery;
use App\Transformers\CustomerListTransformer;
use Illuminate\Database\QueryException;
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

        if (Customer::query()->where('name', $customer->name)->exists()) {
            return $this->restoreConflictResponse();
        }

        try {
            $customer->restore();
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            return $this->restoreConflictResponse();
        }

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

    private function restoreConflictResponse(): RedirectResponse
    {
        return to_route('customers.trash.index')
            ->with('error', __('Customer cannot be restored while another active customer uses this name.'));
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;

        return ($errorInfo[0] ?? $exception->getCode()) === '23000'
            && in_array((int) ($errorInfo[1] ?? 0), [19, 1062], true);
    }
}
