<?php

namespace Tests\Feature\Users;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_user_stamps_the_actor(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $record = User::factory()->create();

        $this->assertSame($actor->id, $record->created_by);
        $this->assertSame($actor->id, $record->updated_by);
        $this->assertNull($record->deleted_by);
    }

    public function test_changing_a_user_stamps_the_editor_and_keeps_the_author(): void
    {
        $author = User::factory()->create();
        $editor = User::factory()->create();
        $this->actingAs($author);
        $record = User::factory()->create();
        $this->actingAs($editor);
        $record->update(['notes' => 'Rewritten notes']);

        $this->assertSame($author->id, $record->refresh()->created_by);
        $this->assertSame($editor->id, $record->updated_by);
    }

    public function test_a_write_without_authentication_keeps_the_existing_trail(): void
    {
        $author = User::factory()->create();
        $this->actingAs($author);
        $record = User::factory()->create();
        $this->app['auth']->forgetGuards();
        $record->update(['notes' => 'Rewritten notes']);

        $this->assertSame($author->id, $record->refresh()->created_by);
        $this->assertSame($author->id, $record->updated_by);
    }

    public function test_trashing_a_user_stamps_the_actor(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $record = User::factory()->create();
        $record->delete();

        $this->assertSoftDeleted($record);
        $this->assertSame($actor->id, $record->refresh()->deleted_by);
    }

    public function test_trashing_without_authentication_keeps_the_author(): void
    {
        $author = User::factory()->create();
        $this->actingAs($author);
        $record = User::factory()->create();
        $this->app['auth']->forgetGuards();
        $record->delete();

        $this->assertSame($author->id, $record->refresh()->created_by);
        $this->assertNull($record->deleted_by);
    }

    public function test_restoring_a_user_clears_the_deletion_actor(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $record = User::factory()->create();
        $record->delete();
        $record->restore();

        $this->assertNotSoftDeleted($record->refresh());
        $this->assertNull($record->deleted_by);
    }

    public function test_audit_columns_are_not_mass_assignable(): void
    {
        foreach (['created_by', 'updated_by', 'deleted_by'] as $column) {
            $this->assertFalse((new User)->isFillable($column));
        }
    }
}
