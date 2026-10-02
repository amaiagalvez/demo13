<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Customer;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Customer $customer): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Customer $customer): bool
    {
        return true;
    }

    public function delete(User $user, Customer $customer): bool
    {
        return true;
    }

    public function restore(User $user, Customer $customer): bool
    {
        return true;
    }

    public function forceDelete(User $user, Customer $customer): bool
    {
        return true;
    }

    /**
     * Determine whether the user can deactivate the model.
     */
    public function deactivate(User $user, Customer $customer): bool
    {
        return true;
    }

    /**
     * Determine whether the user can reactivate the model.
     */
    public function reactivate(User $user, Customer $customer): bool
    {
        return true;
    }
}
