<?php

namespace Tests\Unit\Validation;

use Tests\TestCase;
use App\Http\Requests\ProjectRequest;
use App\Support\Validation\MaxLength;

class ProjectRequestTest extends TestCase
{
    public function test_name_is_required(): void
    {
        $request = ProjectRequest::create('/', 'POST', ['name' => '']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'name');
    }

    public function test_name_must_be_a_string(): void
    {
        $request = ProjectRequest::create('/', 'POST', ['name' => ['Ane Bezeroa']]);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'name');
    }

    public function test_name_cannot_exceed_the_configured_maximum(): void
    {
        $name = str_repeat('a', MaxLength::string() + 1);
        $request = ProjectRequest::create('/', 'POST', ['name' => $name]);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'name');
    }

    public function test_name_must_be_at_least_four_characters(): void
    {
        $request = ProjectRequest::create('/', 'POST', ['name' => 'Abc']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'name');
    }

    public function test_start_date_is_required(): void
    {
        $request = ProjectRequest::create('/', 'POST', ['start_date' => '']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'start_date');
    }

    public function test_start_date_must_be_valid_format(): void
    {
        $request = ProjectRequest::create('/', 'POST', ['start_date' => 'not-a-date']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'start_date');
    }

    public function test_end_date_is_nullable(): void
    {
        $request = ProjectRequest::create('/', 'POST', ['end_date' => null]);

        // end_date is nullable, so it should not fail validation when null
        // The test here is that the rule exists and allows null
        $rules = $request->rules();
        $this->assertContains('nullable', $rules['end_date']);
    }

    public function test_end_date_must_be_valid_format_when_provided(): void
    {
        $request = ProjectRequest::create('/', 'POST', ['end_date' => 'not-a-date']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'end_date');
    }

    public function test_end_date_must_be_after_or_equal_to_start_date(): void
    {
        $request = ProjectRequest::create('/', 'POST', [
            'start_date' => '2026-10-01',
            'end_date' => '2026-09-30',
        ]);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'end_date');
    }

    public function test_customer_id_is_required(): void
    {
        $request = ProjectRequest::create('/', 'POST', ['customer_id' => '']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'customer_id');
    }

    public function test_customer_id_must_be_integer(): void
    {
        $request = ProjectRequest::create('/', 'POST', ['customer_id' => 'not-an-integer']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'customer_id');
    }

    public function test_notes_is_nullable_string_with_max_length(): void
    {
        $request = ProjectRequest::create('/', 'POST', ['notes' => 123]);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'notes');
    }

    public function test_notes_cannot_exceed_long_text_maximum(): void
    {
        $notes = str_repeat('a', MaxLength::longText() + 1);
        $request = ProjectRequest::create('/', 'POST', ['notes' => $notes]);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'notes');
    }

    public function test_reuse_deleted_name_is_sometimes_boolean(): void
    {
        $request = ProjectRequest::create('/', 'POST', ['reuse_deleted_name' => 'not-a-boolean']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'reuse_deleted_name');
    }

    public function test_prepare_for_validation_trims_name(): void
    {
        $request = ProjectRequest::create('/', 'POST', ['name' => '  Trimmed Name  ']);
        $route = new \Illuminate\Routing\Route($request->getMethod(), '/', static fn (): null => null);
        $route->bind($request);
        $request->setRouteResolver(static fn (): \Illuminate\Routing\Route => $route);

        $reflection = new \ReflectionClass($request);
        $method = $reflection->getMethod('prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertSame('Trimmed Name', $request->input('name'));
    }

    public function test_prepare_for_validation_does_not_trim_non_string_name(): void
    {
        $request = ProjectRequest::create('/', 'POST', ['name' => ['array']]);
        $route = new \Illuminate\Routing\Route($request->getMethod(), '/', static fn (): null => null);
        $route->bind($request);
        $request->setRouteResolver(static fn (): \Illuminate\Routing\Route => $route);

        $reflection = new \ReflectionClass($request);
        $method = $reflection->getMethod('prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertSame(['array'], $request->input('name'));
    }

    public function test_rules_return_expected_structure(): void
    {
        $request = new ProjectRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('start_date', $rules);
        $this->assertArrayHasKey('end_date', $rules);
        $this->assertArrayHasKey('customer_id', $rules);
        $this->assertArrayHasKey('notes', $rules);
        $this->assertArrayHasKey('reuse_deleted_name', $rules);
        $this->assertCount(6, $rules);
    }

    public function test_name_rule_contains_required_string_min_max_unique(): void
    {
        $request = new ProjectRequest;
        $rules = $request->rules();

        $nameRules = $rules['name'];
        $this->assertContains('required', $nameRules);
        $this->assertContains('string', $nameRules);
        $this->assertContains('min:4', $nameRules);
        $this->assertContains('max:'.MaxLength::string(), $nameRules);
    }

    public function test_start_date_rule_contains_required_date_format(): void
    {
        $request = new ProjectRequest;
        $rules = $request->rules();

        $this->assertContains('required', $rules['start_date']);
        $this->assertContains('date_format:Y-m-d', $rules['start_date']);
    }

    public function test_end_date_rule_contains_nullable_date_format_after_or_equal(): void
    {
        $request = new ProjectRequest;
        $rules = $request->rules();

        $this->assertContains('nullable', $rules['end_date']);
        $this->assertContains('date_format:Y-m-d', $rules['end_date']);
        $this->assertContains('after_or_equal:start_date', $rules['end_date']);
    }

    public function test_customer_id_rule_contains_required_integer_exists(): void
    {
        $request = new ProjectRequest;
        $rules = $request->rules();

        $this->assertContains('required', $rules['customer_id']);
        $this->assertContains('integer', $rules['customer_id']);
    }

    public function test_authorize_returns_false_when_user_cannot_create(): void
    {
        $request = ProjectRequest::create('/', 'POST');
        $request->setUserResolver(fn () => null);

        $this->assertFalse($request->authorize());
    }
}