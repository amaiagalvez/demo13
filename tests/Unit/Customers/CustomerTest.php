<?php

namespace Tests\Unit\Customers;

use App\Models\Customer;
use PHPUnit\Framework\TestCase;

class CustomerTest extends TestCase
{
  public function test_name_can_be_mass_assigned_without_a_database(): void
  {
    $customer = new Customer;

    $customer->fill(['name' => 'Ane Bezeroa']);

    $this->assertSame('Ane Bezeroa', $customer->name);
  }

  public function test_only_name_is_mass_assignable(): void
  {
    $customer = new Customer;

    $customer->fill([
      'name' => 'Ane Bezeroa',
      'id' => 42,
    ]);

    $this->assertSame('Ane Bezeroa', $customer->name);
    $this->assertFalse($customer->wasRecentlyCreated);
    $this->assertArrayNotHasKey('id', $customer->getAttributes());
  }
}
