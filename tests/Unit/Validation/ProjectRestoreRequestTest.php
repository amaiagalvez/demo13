<?php

namespace Tests\Unit\Validation;

use Tests\TestCase;
use App\Http\Requests\ProjectRestoreRequest;

class ProjectRestoreRequestTest extends TestCase
{
    public function test_rules_return_expected_structure(): void
    {
        $request = new ProjectRestoreRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('resolve_name_conflict', $rules);
        $this->assertCount(1, $rules);
    }

    public function test_resolve_name_conflict_is_sometimes_boolean(): void
    {
        $request = ProjectRestoreRequest::create('/', 'PATCH', ['resolve_name_conflict' => 'not-a-boolean']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'resolve_name_conflict');
    }

    public function test_resolve_name_conflict_is_optional(): void
    {
        $request = ProjectRestoreRequest::create('/', 'PATCH', []);

        $this->assertRequestValidationPasses($request, static fn (): array => $request->rules());
    }

    public function test_authorize_returns_false_when_user_cannot_restore(): void
    {
        $request = ProjectRestoreRequest::create('/', 'PATCH');
        $request->setUserResolver(fn () => null);

        $this->assertFalse($request->authorize());
    }
}