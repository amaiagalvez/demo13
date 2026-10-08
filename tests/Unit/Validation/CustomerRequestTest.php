<?php

namespace Tests\Unit\Validation;

use Tests\TestCase;
use Illuminate\Routing\Route;
use App\Support\Validation\MaxLength;
use App\Http\Requests\CustomerRequest;

class CustomerRequestTest extends TestCase
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

    public function test_name_must_be_at_least_four_characters(): void
    {
        $request = CustomerRequest::create('/', 'POST', ['name' => 'Abc']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'name');
    }

    public function test_notes_is_nullable_string_with_max_length(): void
    {
        $request = CustomerRequest::create('/', 'POST', ['notes' => 123]);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'notes');
    }

    public function test_notes_cannot_exceed_long_text_maximum(): void
    {
        $notes = str_repeat('a', MaxLength::longText() + 1);
        $request = CustomerRequest::create('/', 'POST', ['notes' => $notes]);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'notes');
    }

    public function test_reuse_deleted_name_is_sometimes_boolean(): void
    {
        $request = CustomerRequest::create('/', 'POST', ['reuse_deleted_name' => 'not-a-boolean']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'reuse_deleted_name');
    }

    public function test_prepare_for_validation_trims_name(): void
    {
        $request = CustomerRequest::create('/', 'POST', ['name' => '  Trimmed Name  ']);
        $route = new Route($request->getMethod(), '/', static fn (): null => null);
        $route->bind($request);
        $request->setRouteResolver(static fn (): Route => $route);

        $reflection = new \ReflectionClass($request);
        $method = $reflection->getMethod('prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertSame('Trimmed Name', $request->input('name'));
    }

    public function test_prepare_for_validation_does_not_trim_non_string_name(): void
    {
        $request = CustomerRequest::create('/', 'POST', ['name' => ['array']]);
        $route = new Route($request->getMethod(), '/', static fn (): null => null);
        $route->bind($request);
        $request->setRouteResolver(static fn (): Route => $route);

        $reflection = new \ReflectionClass($request);
        $method = $reflection->getMethod('prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertSame(['array'], $request->input('name'));
    }

    public function test_rules_return_expected_structure(): void
    {
        $request = new CustomerRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('notes', $rules);
        $this->assertArrayHasKey('reuse_deleted_name', $rules);
        $this->assertCount(3, $rules);
    }

    public function test_name_rule_contains_required_string_min_max_unique(): void
    {
        $request = new CustomerRequest;
        $rules = $request->rules();

        $nameRules = $rules['name'];
        $this->assertContains('required', $nameRules);
        $this->assertContains('string', $nameRules);
        $this->assertContains('min:4', $nameRules);
        $maxRule = 'max:'.MaxLength::string();
        $this->assertContains($maxRule, $nameRules);
        $index = array_search($maxRule, $nameRules, true);
        $this->assertIsInt($index);
        $this->assertStringStartsWith('max:', $nameRules[$index]);
    }

    public function test_authorize_returns_false_when_user_cannot_create(): void
    {
        $request = CustomerRequest::create('/', 'POST');
        $request->setUserResolver(fn () => null);

        $this->assertFalse($request->authorize());
    }
}
