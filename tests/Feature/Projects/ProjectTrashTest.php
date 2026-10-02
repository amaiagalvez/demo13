<?php

namespace Tests\Feature\Projects;

use Tests\TestCase;
use App\Models\Epic;
use App\Models\User;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectTrashTest extends TestCase
{
    use RefreshDatabase;

    public function test_trash_preserves_index_columns_and_appends_the_deletion_date(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo('2026-10-02 12:00:00');
        $project = Project::factory()->create([
            'name' => 'Project with dates',
            'start_date' => '2026-01-03',
            'end_date' => '2026-08-04',
        ]);
        $customer = $project->customer;
        $this->assertNotNull($customer);

        $activeResponse = $this->get(route('projects.index'));
        $project->delete();

        $response = $this->get(route('projects.trash.index'));

        $activeResponse->assertDontSee(__('Deleted at'));
        $response->assertSeeInOrder([
            '<thead', __('Actions'), __('Name'), __('Customer'), __('Start date'), __('End date'), __('Deleted at'),
        ], false)->assertSeeInOrder(['Project with dates', $customer->name, '2026-01-03', '2026-08-04', '2026-10-02']);
    }

    public function test_deleted_projects_can_be_restored_or_permanently_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        $activeProject = Project::factory()->create(['name' => 'Active project']);
        $deletedProject = Project::factory()->trashed()->create(['name' => 'Deleted project']);

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Active project')
            ->assertDontSee('Deleted project');

        $this->get(route('projects.trash.index'))
            ->assertOk()
            ->assertSee('Deleted project')
            ->assertDontSee('Active project');

        $this->patch(route('projects.trash.restore', $deletedProject->id))
            ->assertRedirect(route('projects.trash.index'));
        $this->assertNotSoftDeleted($deletedProject);

        $this->delete(route('projects.destroy', $activeProject))
            ->assertRedirect(route('projects.index'));
        $this->delete(route('projects.trash.destroy', $activeProject->id))
            ->assertRedirect(route('projects.trash.index'));
        $this->assertDatabaseMissing('projects', ['id' => $activeProject->id]);

        $this->delete(route('projects.destroy', $deletedProject))
            ->assertRedirect(route('projects.index'));
        $this->delete(route('projects.trash.destroy', $deletedProject->id))
            ->assertRedirect(route('projects.trash.index'));
        $this->assertDatabaseMissing('projects', ['id' => $deletedProject->id]);
    }

    public function test_deleted_project_with_epics_cannot_be_permanently_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        Epic::factory()->for($project)->create();
        $project->delete();

        $this->delete(route('projects.trash.destroy', $project->id))
            ->assertRedirect(route('projects.trash.index'))
            ->assertSessionHas('error', __('Project cannot be permanently deleted while it has epics.'));

        $this->assertSoftDeleted($project);
    }

    public function test_project_restore_conflict_resolution_reports_that_no_duplicate_was_created(): void
    {
        $this->actingAs(User::factory()->create());
        $deletedProject = Project::factory()->trashed()->create(['name' => 'Restorable project']);

        $this->patch(route('projects.trash.restore', $deletedProject->id), [
            'resolve_name_conflict' => '1',
        ])
            ->assertRedirect(route('projects.trash.index'))
            ->assertSessionHas(
                'status',
                __('Project restored successfully. No new project was created with the repeated name.'),
            );

        $this->assertDatabaseCount('projects', 1);
        $this->assertNotSoftDeleted($deletedProject);
    }

    public function test_trash_lists_most_recently_deleted_projects_first(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo('2026-10-02 12:00:00');
        $olderProject = Project::factory()->trashed()->create(['name' => 'Older deleted project']);
        $newerProject = Project::factory()->trashed()->create(['name' => 'Newer deleted project']);
        $tiedProject = Project::factory()->trashed()->create(['name' => 'Tied deleted project']);
        $olderProject->forceFill(['deleted_at' => now()->subHour()])->saveQuietly();

        $response = $this->get(route('projects.trash.index'));

        $response->assertSeeInOrder([
            $newerProject->name, $tiedProject->name, $olderProject->name,
        ]);
    }

    public function test_project_cannot_be_restored_when_an_active_project_uses_its_name(): void
    {
        $this->actingAs(User::factory()->create());
        $deletedProject = Project::factory()->trashed()->create([
            'name' => 'Repeated project name',
        ]);
        $activeProject = Project::factory()->create(['name' => 'Repeated project name']);

        $this->patch(route('projects.trash.restore', $deletedProject->id))
            ->assertRedirect(route('projects.trash.index'));

        $this->get(route('projects.trash.index'))
            ->assertSee(__('Project cannot be restored while another active project uses this name.'));

        $this->assertModelExists($activeProject);
        $this->assertSoftDeleted($deletedProject);
    }

    public function test_restore_returns_conflict_when_name_becomes_active_after_precheck(): void
    {
        $this->actingAs(User::factory()->create());
        $deletedProject = Project::factory()->trashed()->create(['name' => 'Concurrent restore project']);
        $competitorCreated = false;

        DB::listen(static function (QueryExecuted $query) use (&$competitorCreated): void {
            if (
                $competitorCreated
                || ! str_starts_with(strtolower(ltrim($query->sql)), 'select')
                || ! str_contains(strtolower($query->sql), 'projects')
                || ! in_array('Concurrent restore project', $query->bindings, true)
            ) {
                return;
            }

            $competitorCreated = true;
            Project::factory()->create(['name' => 'Concurrent restore project']);
        });

        $this->patch(route('projects.trash.restore', $deletedProject->id))
            ->assertRedirect(route('projects.trash.index'))
            ->assertSessionHas(
                'error',
                __('Project cannot be restored while another active project uses this name.'),
            );

        $this->assertSoftDeleted($deletedProject);
        $this->assertDatabaseHas('projects', [
            'name' => 'Concurrent restore project',
            'deleted_at' => null,
        ]);
    }
}
