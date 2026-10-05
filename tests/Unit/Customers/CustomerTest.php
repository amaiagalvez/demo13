<?php

namespace Tests\Unit\Customers;

use Tests\TestCase;
use App\Models\Customer;

class CustomerTest extends TestCase
{
    public function test_customer_fields_can_be_mass_assigned_without_a_database(): void
    {
        $customer = new Customer;

        $customer->fill([
            'name' => 'Ane Bezeroa',
            'notes' => 'Eskualdeko bezeroa',
        ]);

        $this->assertSame('Ane Bezeroa', $customer->getAttributes()['name']);
        $this->assertSame('Eskualdeko bezeroa', $customer->getAttributes()['notes']);
    }

    public function test_only_declared_customer_attributes_are_mass_assignable(): void
    {
        $customer = new Customer;

        $customer->fill([
            'name' => 'Ane Bezeroa',
            'notes' => 'Eskualdeko bezeroa',
            'id' => 42,
            'active' => false,
        ]);

        $this->assertSame('Ane Bezeroa', $customer->getAttributes()['name']);
        $this->assertTrue($customer->active);
        $this->assertFalse($customer->wasRecentlyCreated);
        $this->assertArrayNotHasKey('id', $customer->getAttributes());
    }

    public function test_a_new_customer_is_active_without_a_database(): void
    {
        $customer = new Customer;

        $this->assertTrue($customer->active);
    }
}
