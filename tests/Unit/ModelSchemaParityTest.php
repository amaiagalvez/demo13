<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use ReflectionClass;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Attributes\Fillable;

class ModelSchemaParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_fillable_attributes_and_casts_match_the_schema(): void
    {
        $user = new User;
        $columns = Schema::getColumnListing($user->getTable());
        $attributes = (new ReflectionClass(User::class))->getAttributes(Fillable::class);
        $fillable = $attributes[0]->getArguments()[0];
        $systemColumns = [
            'id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by',
            'deleted_by', 'active', 'remember_token', 'email_verified_at',
            'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
        ];

        foreach ($fillable as $attribute) {
            $this->assertContains($attribute, $columns);
        }

        foreach (array_diff($columns, $systemColumns) as $column) {
            $this->assertContains($column, $fillable);
        }

        foreach (array_keys($user->getCasts()) as $column) {
            $this->assertContains($column, $columns);
        }

        $this->assertContains('deleted_at', $columns);
        $this->assertSoftDeleted(User::factory()->trashed()->create());
        $this->assertFalse(User::factory()->archived()->make()->active);
        $this->assertArrayHasKey('active', $user->getCasts());
    }
}
