<?php

namespace Tests\Feature\Projects;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectTrashTest extends TestCase
{
    use RefreshDatabase;

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
}
