<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends TrashDestroyRequest<Customer>
 */
final class CustomerDestroyRequest extends TrashDestroyRequest
{
    /**
     * @return Customer
     */
    protected function findTrashed(): Model
    {
        return Customer::onlyTrashed()
            ->findOrFail((int) $this->route('customer'));
    }
}
