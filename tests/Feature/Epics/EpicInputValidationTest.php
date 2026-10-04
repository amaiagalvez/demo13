<?php

namespace Tests\Feature\Epics;

use Tests\TestCase;
use App\Models\Epic;
use App\Models\User;
use App\Models\Project;
use PHPUnit\Framework\Attributes\TestWith;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EpicInputValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_name_and_project_are_required(): void
    {
        $this->actingAs(User::factory()->create());

        $this->from(route('epics.index'))
            ->post(route('epics.store'), [])
            ->assertRedirect(route('epics.index'))
            ->assertSessionHasErrors(['name', 'project_id'])
            ->assertSessionDoesntHaveErrors(['start_date', 'end_date']);
    }

    public function test_epic_can_be_created_without_dates(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();

        $this->post(route('epics.store'), [
            'name' => 'Undated epic',
            'project_id' => $project->id,
        ])->assertRedirect(route('epics.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('epics', [
            'name' => 'Undated epic',
            'start_date' => null,
            'end_date' => null,
        ]);
    }

    public function test_epic_name_must_have_more_than_three_characters(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();

        $this->from(route('epics.index'))
            ->post(route('epics.store'), ['name' => 'Abc', 'project_id' => $project->id])
            ->assertSessionHasErrors([
                'name' => __('validation.min.string', ['attribute' => 'name', 'min' => 4]),
            ]);

        $this->assertDatabaseCount('epics', 0);
    }

    public function test_epic_name_cannot_exceed_255_characters_over_http(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();

        $this->from(route('epics.index'))
            ->post(route('epics.store'), [
                'name' => str_repeat('a', 256),
                'project_id' => $project->id,
            ])
            ->assertRedirect(route('epics.index'))
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseCount('epics', 0);
    }

    public function test_epic_name_must_be_a_string_over_http(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();

        $this->from(route('epics.index'))
            ->post(route('epics.store'), [
                'name' => ['Ane Bezeroa'],
                'project_id' => $project->id,
            ])
            ->assertRedirect(route('epics.index'))
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseCount('epics', 0);
    }

    public function test_epic_name_must_be_unique_within_the_project(): void
    {
        $this->actingAs(User::factory()->create());
        $epic = Epic::factory()->create(['name' => 'Existing epic']);

        $this->from(route('epics.index'))
            ->post(route('epics.store'), ['name' => 'Existing epic', 'project_id' => $epic->project_id])
            ->assertRedirect(route('epics.index'))
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseCount('epics', 1);
    }

    public function test_store_trims_epic_name_before_unique_validation(): void
    {
        $this->actingAs(User::factory()->create());
        $existingEpic = Epic::factory()->create(['name' => 'Existing epic']);

        $this->from(route('epics.index'))
            ->post(route('epics.store'), [
                'name' => '  Existing epic  ',
                'project_id' => $existingEpic->project_id,
            ])
            ->assertRedirect(route('epics.index'))
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseCount('epics', 1);
    }

    public function test_epic_name_can_repeat_in_another_project(): void
    {
        $this->actingAs(User::factory()->create());
        Epic::factory()->create(['name' => 'Existing epic']);
        $otherProject = Project::factory()->create();

        $this->post(route('epics.store'), ['name' => 'Existing epic', 'project_id' => $otherProject->id])
            ->assertRedirect(route('epics.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('epics', 2);
    }

    public function test_epic_name_cannot_be_changed_to_another_epic_name_in_the_same_project(): void
    {
        $this->actingAs(User::factory()->create());
        $existingEpic = Epic::factory()->create(['name' => 'Existing epic']);
        $existingProject = $existingEpic->project;

        if ($existingProject === null) {
            self::fail('The existing epic must belong to a project.');
        }

        $epicToUpdate = Epic::factory()->for($existingProject)->create(['name' => 'Epic to update']);

        $this->from(route('epics.index'))
            ->put(route('epics.update', $epicToUpdate), [
                'name' => 'Existing epic',
                'project_id' => $epicToUpdate->project_id,
            ])
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseHas('epics', ['id' => $epicToUpdate->id, 'name' => 'Epic to update']);
    }

    public function test_epic_name_can_be_kept_when_updating_the_same_epic(): void
    {
        $this->actingAs(User::factory()->create());
        $epic = Epic::factory()->create(['name' => 'Existing epic']);

        $this->put(route('epics.update', $epic), [
            'name' => 'Existing epic',
            'project_id' => $epic->project_id,
        ])->assertRedirect(route('epics.index'))
            ->assertSessionHasNoErrors();
    }

    public function test_update_trims_epic_name_before_persisting(): void
    {
        $this->actingAs(User::factory()->create());
        $epic = Epic::factory()->create(['name' => 'Original epic']);

        $this->put(route('epics.update', $epic), [
            'name' => '  Trimmed epic  ',
            'project_id' => $epic->project_id,
        ])->assertRedirect(route('epics.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('epics', [
            'id' => $epic->id,
            'name' => 'Trimmed epic',
        ]);
    }

    #[TestWith(['2026-10-01'])]
    #[TestWith(['2026-09-30'])]
    public function test_end_date_must_be_after_start_date(string $endDate): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();

        $this->from(route('epics.index'))
            ->post(route('epics.store'), [
                'name' => 'Invalid dates',
                'start_date' => '2026-10-01',
                'end_date' => $endDate,
                'project_id' => $project->id,
            ])
            ->assertSessionHasErrors(['end_date']);

        $this->assertDatabaseCount('epics', 0);
    }

    public function test_end_date_requires_a_start_date(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();

        $this->from(route('epics.index'))
            ->post(route('epics.store'), [
                'name' => 'End without start',
                'end_date' => '2026-10-01',
                'project_id' => $project->id,
            ])
            ->assertSessionHasErrors(['start_date']);

        $this->assertDatabaseCount('epics', 0);
    }

    public function test_start_date_can_be_set_without_end_date(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();

        $this->post(route('epics.store'), [
            'name' => 'Open-ended epic',
            'start_date' => '2026-10-01',
            'project_id' => $project->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('epics', ['name' => 'Open-ended epic', 'end_date' => null]);
    }

    public function test_inactive_projects_cannot_receive_epics(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->inactive()->create();

        $this->from(route('epics.index'))
            ->post(route('epics.store'), ['name' => 'Orphan epic', 'project_id' => $project->id])
            ->assertSessionHasErrors(['project_id']);

        $this->assertDatabaseMissing('epics', ['name' => 'Orphan epic']);
    }

    /**
     * The project an epic already belongs to stays valid while editing, even once it has been
     * deactivated, so a form that only renames the epic can still be saved.
     */
    public function test_an_epic_can_keep_its_inactive_project_while_being_edited(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->inactive()->create();
        $epic = Epic::factory()->for($project)->create();

        $this->from(route('epics.index'))
            ->put(route('epics.update', $epic), [
                'name' => 'Renamed epic',
                'project_id' => $project->id,
            ])
            ->assertRedirect(route('epics.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('epics', [
            'id' => $epic->id,
            'name' => 'Renamed epic',
            'project_id' => $project->id,
        ]);
    }

    public function test_an_epic_cannot_be_moved_to_an_inactive_project(): void
    {
        $this->actingAs(User::factory()->create());
        $epic = Epic::factory()->create();
        $inactive = Project::factory()->inactive()->create();

        $this->from(route('epics.index'))
            ->put(route('epics.update', $epic), [
                'name' => 'Moved epic',
                'project_id' => $inactive->id,
            ])
            ->assertSessionHasErrors(['project_id']);

        $this->assertDatabaseHas('epics', [
            'id' => $epic->id,
            'project_id' => $epic->project_id,
        ]);
    }

    public function test_epic_cannot_belong_to_a_deleted_project(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->trashed()->create();

        $this->from(route('epics.index'))
            ->post(route('epics.store'), ['name' => 'Orphan epic', 'project_id' => $project->id])
            ->assertSessionHasErrors(['project_id']);
    }

    public function test_deleted_epic_name_conflict_can_be_resolved_by_creating_a_new_epic(): void
    {
        $this->actingAs(User::factory()->create());
        $deletedEpic = Epic::factory()->trashed()->create(['name' => 'Deleted epic']);
        $payload = ['name' => 'Deleted epic', 'project_id' => $deletedEpic->project_id];

        $this->post(route('epics.store'), $payload)
            ->assertRedirect(route('epics.index'))
            ->assertSessionHas('deleted_epic_conflict', ['id' => $deletedEpic->id, 'name' => 'Deleted epic']);

        $this->get(route('epics.index'))
            ->assertOk()
            ->assertSee('epic-name-conflict')
            ->assertSee('epic-conflict-create-new')
            ->assertSee('epic-conflict-restore')
            ->assertSee('aria-labelledby="epic-name-conflict-heading"', false)
            ->assertSee('id="epic-name-conflict-heading"', false);

        $this->assertDatabaseCount('epics', 1);

        $this->post(route('epics.store'), [...$payload, 'reuse_deleted_name' => '1'])
            ->assertRedirect(route('epics.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('epics', ['name' => 'Deleted epic', 'deleted_at' => null]);
        $this->assertSoftDeleted($deletedEpic);
    }
}
