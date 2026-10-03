<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;
use App\Http\Requests\CustomerListRequest;
use App\Queries\Customers\CustomerListQuery;
use App\Http\Requests\CustomerRestoreRequest;
use App\Transformers\CustomerListTransformer;
use App\Support\Database\UniqueConstraintViolation;

class CustomerTrashController extends Controller
{
    public function index(
        CustomerListRequest $request,
        CustomerListQuery $query,
        CustomerListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $customers = $query->trashed($search);

        return $this->listView($request, 'customers.list', [
            'customers' => $customers,
            'list' => $transformer->trash($customers, $search, $query->trashedCount()),
        ]);
    }

    public function restore(CustomerRestoreRequest $request): RedirectResponse
    {
        $customer = $request->customer();

        if (Customer::query()->where('name', $customer->name)->exists()) {
            return $this->restoreConflictResponse();
        }

        try {
            $customer->restore();
        } catch (QueryException $exception) {
            if (! UniqueConstraintViolation::causedBy($exception)) {
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

        $deleted = DB::transaction(static function () use ($customer): bool {
            $lockedCustomer = Customer::onlyTrashed()
                ->whereKey($customer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            return $lockedCustomer->forceDelete() !== false;
        });

        if (! $deleted) {
            return to_route('customers.trash.index')
                ->with('error', __('Customer cannot be permanently deleted while it has projects.'));
        }

        return to_route('customers.trash.index')->with('status', __('Customer permanently deleted.'));
    }

    private function restoreConflictResponse(): RedirectResponse
    {
        return to_route('customers.trash.index')
            ->with('error', __('Customer cannot be restored while another active customer uses this name.'));
    }
}
