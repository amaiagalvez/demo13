<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Basics13\Http\Requests\RestoreRequest;

/**
 * @extends RestoreRequest<Customer>
 */
final class CustomerRestoreRequest extends RestoreRequest
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
