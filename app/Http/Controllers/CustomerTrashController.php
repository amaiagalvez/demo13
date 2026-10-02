<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\View\View;
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

        return view('customers.list', [
            'customers' => $customers,
            'list' => $transformer->trash($customers, $search),
        ])->fragmentIf($request->hasHeader('X-List-Fragment'), 'list-results');
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

        if ($customer->projects()->withTrashed()->exists()) {
            return to_route('customers.trash.index')
                ->with('error', __('Customer cannot be permanently deleted while it has projects.'));
        }

        $customer->forceDelete();

        return to_route('customers.trash.index')->with('status', __('Customer permanently deleted.'));
    }

    private function restoreConflictResponse(): RedirectResponse
    {
        return to_route('customers.trash.index')
            ->with('error', __('Customer cannot be restored while another active customer uses this name.'));
    }
}
