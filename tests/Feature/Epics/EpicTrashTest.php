<?php

namespace Tests\Feature\Epics;

use Tests\TestCase;
use App\Models\Epic;
use App\Models\User;
use App\Models\EpicComment;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EpicTrashTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleted_epics_can_be_restored_or_permanently_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        $activeEpic = Epic::factory()->create(['name' => 'Active epic']);
        $deletedEpic = Epic::factory()->trashed()->create(['name' => 'Deleted epic']);

        $this->get(route('epics.index'))
            ->assertOk()
            ->assertSee('Active epic')
            ->assertDontSee('Deleted epic');

        $this->get(route('epics.trash.index'))
            ->assertOk()
            ->assertSee('Deleted epic')
            ->assertDontSee('Active epic');

        $this->patch(route('epics.trash.restore', $deletedEpic->id))
            ->assertRedirect(route('epics.trash.index'))
            ->assertSessionHas('status', __('Epic restored successfully.'));
        $this->assertNotSoftDeleted($deletedEpic);

        $this->delete(route('epics.destroy', $activeEpic))->assertRedirect(route('epics.index'));
        $this->delete(route('epics.trash.destroy', $activeEpic->id))
            ->assertRedirect(route('epics.trash.index'))
            ->assertSessionHas('status', __('Epic permanently deleted.'));
        $this->assertDatabaseMissing('epics', ['id' => $activeEpic->id]);
    }

    public function test_active_epics_cannot_be_permanently_deleted_from_the_trash_route(): void
    {
        $this->actingAs(User::factory()->create());
        $epic = Epic::factory()->create();

        $this->delete(route('epics.trash.destroy', $epic->id))->assertNotFound();

        $this->assertModelExists($epic);
    }

    public function test_permanently_deleting_an_epic_removes_its_comments(): void
    {
        $this->actingAs(User::factory()->create());
        $epic = Epic::factory()->trashed()->create();
        $comment = EpicComment::factory()->for($epic)->create();

        $this->delete(route('epics.trash.destroy', $epic->id));

        $this->assertModelMissing($comment);
    }

    public function test_epic_cannot_be_restored_when_an_active_epic_in_the_same_project_uses_its_name(): void
    {
        $this->actingAs(User::factory()->create());
        $deletedEpic = Epic::factory()->trashed()->create(['name' => 'Repeated epic']);
        Epic::factory()->for($deletedEpic->project)->create(['name' => 'Repeated epic']);

        $this->patch(route('epics.trash.restore', $deletedEpic->id))
            ->assertRedirect(route('epics.trash.index'))
            ->assertSessionHas(
                'error',
                __('Epic cannot be restored while another active epic in the same project uses this name.'),
            );

        $this->assertSoftDeleted($deletedEpic);
    }

    public function test_epic_can_be_restored_when_its_name_is_used_only_in_another_project(): void
    {
        $this->actingAs(User::factory()->create());
        $deletedEpic = Epic::factory()->trashed()->create(['name' => 'Repeated epic']);
        Epic::factory()->create(['name' => 'Repeated epic']);

        $this->patch(route('epics.trash.restore', $deletedEpic->id))
            ->assertSessionHas('status', __('Epic restored successfully.'));

        $this->assertNotSoftDeleted($deletedEpic);
    }

    public function test_restore_returns_conflict_when_name_becomes_active_after_precheck(): void
    {
        $this->actingAs(User::factory()->create());
        $deletedEpic = Epic::factory()->trashed()->create(['name' => 'Concurrent restore epic']);
        $competitorCreated = false;

        DB::listen(static function (QueryExecuted $query) use ($deletedEpic, &$competitorCreated): void {
            if (
                $competitorCreated
                || ! str_starts_with(strtolower(ltrim($query->sql)), 'select')
                || ! str_contains(strtolower($query->sql), 'epics')
                || ! in_array('Concurrent restore epic', $query->bindings, true)
            ) {
                return;
            }

            $competitorCreated = true;
            Epic::factory()->for($deletedEpic->project)->create(['name' => 'Concurrent restore epic']);
        });

        $this->patch(route('epics.trash.restore', $deletedEpic->id))
            ->assertRedirect(route('epics.trash.index'))
            ->assertSessionHas(
                'error',
                __('Epic cannot be restored while another active epic in the same project uses this name.'),
            );

        $this->assertSoftDeleted($deletedEpic);
        $this->assertDatabaseHas('epics', ['name' => 'Concurrent restore epic', 'deleted_at' => null]);
    }
}
