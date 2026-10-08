<?php

namespace Tests\Unit\Policies;

use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Policies\CustomerPolicy;

class CustomerPolicyTest extends TestCase
{
    private CustomerPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new CustomerPolicy;
    }

    public function test_view_any_returns_true(): void
    {
        $user = User::factory()->make();

        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_create_returns_true(): void
    {
        $user = User::factory()->make();

        $this->assertTrue($this->policy->create($user));
    }

    public function test_update_returns_true(): void
    {
        $user = User::factory()->make();
        $customer = Customer::factory()->make();

        $this->assertTrue($this->policy->update($user, $customer));
    }

    public function test_delete_returns_true(): void
    {
        $user = User::factory()->make();
        $customer = Customer::factory()->make();

        $this->assertTrue($this->policy->delete($user, $customer));
    }

    public function test_restore_returns_true(): void
    {
        $user = User::factory()->make();
        $customer = Customer::factory()->make();

        $this->assertTrue($this->policy->restore($user, $customer));
    }

    public function test_force_delete_returns_true(): void
    {
        $user = User::factory()->make();
        $customer = Customer::factory()->make();

        $this->assertTrue($this->policy->forceDelete($user, $customer));
    }

    public function test_archive_returns_true(): void
    {
        $user = User::factory()->make();
        $customer = Customer::factory()->make();

        $this->assertTrue($this->policy->archive($user, $customer));
    }

    public function test_activate_returns_true(): void
    {
        $user = User::factory()->make();
        $customer = Customer::factory()->make();

        $this->assertTrue($this->policy->activate($user, $customer));
    }
}
