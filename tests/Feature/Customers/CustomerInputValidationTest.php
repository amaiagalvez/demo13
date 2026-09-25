<?php

namespace Tests\Feature\Customers;

use Tests\TestCase;
use Illuminate\Routing\Route;
use App\Http\Requests\CustomerRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as LaravelValidator;

class CustomerInputValidationTest extends TestCase
{
    public function test_name_is_required(): void
    {
        $this->assertValidationFails(['name' => ''], 'name');
    }

    public function test_name_must_be_a_string(): void
    {
        $this->assertValidationFails(['name' => ['Ane Bezeroa']], 'name');
    }

    public function test_name_cannot_exceed_255_characters(): void
    {
        $this->assertValidationFails(['name' => str_repeat('a', 256)], 'name');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertValidationFails(array $data, string $field): void
    {
        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey($field, $validator->errors()->toArray());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validator(array $data): LaravelValidator
    {
        $request = CustomerRequest::create('/', 'POST', $data);
        $route = new Route('POST', '/', fn (): null => null);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        return Validator::make($data, $request->rules());
    }
}
