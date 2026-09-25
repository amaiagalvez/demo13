<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
  public function index(): View
  {
    return view('customers.index', [
      'customers' => Customer::query()->latest()->get(),
    ]);
  }

  public function create(): RedirectResponse
  {
    return to_route('customers.index');
  }

  public function store(Request $request): RedirectResponse
  {
    $validated = $request->validate([
      'name' => ['required', 'string'],
    ]);

    Customer::create($validated);

    return to_route('customers.index')->with('status', __('Customer created successfully.'));
  }

  public function edit(Customer $customer): RedirectResponse
  {
    return to_route('customers.index');
  }

  public function update(Request $request, Customer $customer): RedirectResponse
  {
    $validated = $request->validate([
      'name' => ['required', 'string'],
    ]);

    $customer->update($validated);

    return to_route('customers.index')->with('status', __('Customer updated successfully.'));
  }

  public function destroy(Customer $customer): RedirectResponse
  {
    $customer->delete();

    return to_route('customers.index')->with('status', __('Customer deleted successfully.'));
  }
}
