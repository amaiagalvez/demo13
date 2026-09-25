<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerTrashController extends Controller
{
    public function index(): View
    {
        return view('customers.trash', [
            'customers' => Customer::onlyTrashed()->latest('deleted_at')->paginate(),
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
