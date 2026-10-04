<?php

namespace Tests\Feature\Customers;

use Tests\TestCase;
use App\Support\Validation\MaxLength;
use App\Http\Requests\CustomerRequest;

class CustomerInputValidationTest extends TestCase
{
    public function test_name_is_required(): void
    {
        $request = CustomerRequest::create('/', 'POST', ['name' => '']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'name');
    }

    public function test_name_must_be_a_string(): void
    {
        $request = CustomerRequest::create('/', 'POST', ['name' => ['Ane Bezeroa']]);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'name');
    }

    public function test_name_cannot_exceed_the_configured_maximum(): void
    {
        $name = str_repeat('a', MaxLength::string() + 1);
        $request = CustomerRequest::create('/', 'POST', ['name' => $name]);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'name');
    }
}
