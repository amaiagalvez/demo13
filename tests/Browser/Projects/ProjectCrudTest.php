<?php

namespace Tests\Browser\Projects;

use App\Models\User;
use App\Models\Project;
use Tests\DuskTestCase;
use App\Models\Customer;
use Laravel\Dusk\Browser;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class ProjectCrudTest extends DuskTestCase
{
    private const PROJECT_NAME_SELECTOR = 'dialog[open] [data-test="project-name"]';

    private const PROJECT_STATUS_SELECTOR = '[data-test="project-status"]';

    use DatabaseMigrations;

    public function test_project_can_be_created_updated_and_deleted_from_the_drawer(): void
    {
        $user = User::factory()->create();
        $suffix = Str::uuid()->toString();
        $customerName = 'Dusk customer '.$suffix;
        $projectName = 'Dusk project '.$suffix;
        $updatedProjectName = 'Updated project '.$suffix;
        Customer::factory()->create(['name' => $customerName]);
        $projectId = null;

        $this->browse(function (Browser $browser) use (
            $user,
            $customerName,
            $projectName,
            $updatedProjectName,
            &$projectId,
        ): void {
            $browser->loginAs($user)
                ->visit('/projects')
                ->waitUntil('typeof window.jQuery?.fn?.select2 === "function"', 10)
                ->click('[data-test="project-create-button"]')
                ->waitFor(self::PROJECT_NAME_SELECTOR)
                ->type(self::PROJECT_NAME_SELECTOR, $projectName)
                ->click('#select2-project-customer-id-container')
                ->type('dialog[open] .select2-container--open .select2-search__field', $customerName)
                ->waitUntil(
                    'document.querySelector("dialog[open] .select2-results")?.textContent.includes('.json_encode($customerName).')',
                    10,
                )
                ->click('dialog[open] .select2-results__option--selectable');

            $browser->script(<<<'JS'
                const input = document.querySelector('dialog[open] [data-test="project-start-date"]');
                input.value = '2026-10-01';
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                JS);

            $browser->click('dialog[open] [data-test="project-submit"]')
                ->waitFor(self::PROJECT_STATUS_SELECTOR)
                ->assertSee($projectName);

            $project = Project::query()->where('name', $projectName)->firstOrFail();
            $projectId = $project->id;

            $browser->click("[data-test='project-edit-{$project->id}']")
                ->waitFor(self::PROJECT_NAME_SELECTOR)
                ->clear(self::PROJECT_NAME_SELECTOR)
                ->type(self::PROJECT_NAME_SELECTOR, $updatedProjectName)
                ->click('dialog[open] [data-test="project-submit"]')
                ->waitFor(self::PROJECT_STATUS_SELECTOR)
                ->assertSee($updatedProjectName)
                ->click("[data-test='project-delete-{$project->id}']")
                ->waitFor('dialog[open] [data-test="project-confirm-submit"]')
                ->click('dialog[open] [data-test="project-confirm-submit"]')
                ->waitFor(self::PROJECT_STATUS_SELECTOR)
                ->assertDontSee($updatedProjectName);
        });

        $this->assertNotNull($projectId);
        $this->assertSoftDeleted('projects', ['id' => $projectId]);
    }
}
