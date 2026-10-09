<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Model;
use App\Http\Requests\CustomerListRequest;
use App\Queries\Customers\CustomerListQuery;
use App\Http\Requests\CustomerDestroyRequest;
use App\Http\Requests\CustomerRestoreRequest;
use App\Transformers\CustomerListTransformer;
use Basics13\Http\Controllers\TrashController;

/**
 * @extends TrashController<Customer>
 */
class CustomerTrashController extends TrashController
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
            'list' => $transformer->trash(
                $customers,
                $search,
                $query->stateCounts(trashedTotal: $search === '' ? $customers->total() : null),
            ),
        ]);
    }

    public function restore(CustomerRestoreRequest $request): RedirectResponse
    {
        return $this->restoreTrashed($request);
    }

    public function destroy(CustomerDestroyRequest $request): RedirectResponse
    {
        return $this->destroyTrashed($request);
    }

    /**
     * @param  Customer  $record
     */
    protected function restoreTrashedRecord(Model $record): void
    {
        $record->restore();
    }

    /**
     * @param  Customer  $record
     */
    protected function nameIsTaken(Model $record): bool
    {
        return $this->takenBy(Customer::query(), $record->name);
    }

    protected function recordClass(): string
    {
        return Customer::class;
    }

    protected function trashRoute(): string
    {
        return 'customers.trash.index';
    }
}
