<?php

namespace Tests\Unit\Validation;

use Tests\TestCase;
use App\Http\Requests\CustomerRestoreRequest;

class CustomerRestoreRequestTest extends TestCase
{
    public function test_rules_return_expected_structure(): void
    {
        $request = new CustomerRestoreRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('resolve_name_conflict', $rules);
        $this->assertCount(1, $rules);
    }

    public function test_resolve_name_conflict_is_sometimes_boolean(): void
    {
        $request = CustomerRestoreRequest::create('/', 'PATCH', ['resolve_name_conflict' => 'not-a-boolean']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'resolve_name_conflict');
    }

    public function test_resolve_name_conflict_is_optional(): void
    {
        $request = CustomerRestoreRequest::create('/', 'PATCH', []);

        // No error should be thrown for missing optional field
        $this->assertRequestValidationPasses($request, static fn (): array => $request->rules());
    }

    public function test_authorize_returns_false_when_user_cannot_restore(): void
    {
        $request = CustomerRestoreRequest::create('/', 'PATCH');
        $request->setUserResolver(fn () => null);

        $this->assertFalse($request->authorize());
    }
}
