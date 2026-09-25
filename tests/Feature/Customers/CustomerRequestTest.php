<?php

namespace Tests\Feature\Customers;

use Tests\TestCase;
use App\Models\Customer;
use Illuminate\Routing\Route;
use App\Http\Requests\CustomerRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\Validator as LaravelValidator;

class CustomerRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_name_may_contain_255_characters(): void
    {
        $validator = $this->validator(['name' => str_repeat('a', 255)]);

        $this->assertTrue($validator->passes());
    }

    public function test_name_must_be_unique_when_creating(): void
    {
        Customer::factory()->create(['name' => 'Ane Bezeroa']);

        $this->assertValidationFails(['name' => 'Ane Bezeroa'], 'name');
    }

    public function test_customer_can_keep_its_name_when_updating(): void
    {
        $customer = Customer::factory()->create(['name' => 'Ane Bezeroa']);

        $validator = $this->validator(['name' => 'Ane Bezeroa'], $customer);

        $this->assertTrue($validator->passes());
    }

    public function test_customer_cannot_use_another_customers_name(): void
    {
        Customer::factory()->create(['name' => 'Ane Bezeroa']);
        $customer = Customer::factory()->create(['name' => 'Jon Bezeroa']);

        $this->assertValidationFails(['name' => 'Ane Bezeroa'], 'name', $customer);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertValidationFails(array $data, string $field, ?Customer $customer = null): void
    {
        $validator = $this->validator($data, $customer);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey($field, $validator->errors()->toArray());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validator(array $data, ?Customer $customer = null): LaravelValidator
    {
        $request = CustomerRequest::create('/', $customer ? 'PUT' : 'POST', $data);
        $route = new Route($customer ? 'PUT' : 'POST', '/', fn (): null => null);
        $route->bind($request);

        if ($customer) {
            $route->setParameter('customer', $customer);
        }

        $request->setRouteResolver(fn (): Route => $route);

        return Validator::make($data, $request->rules());
    }
}
