<?php

namespace Tests\Browser\Epics;

use App\Models\Epic;
use App\Models\User;
use App\Models\Project;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class EpicCrudTest extends DuskTestCase
{
    private const EPIC_PROJECT_SELECTOR = 'dialog[open] [data-test="epic-project"]';

    private const EPIC_NAME_SELECTOR = 'dialog[open] [data-test="epic-name"]';

    private const EPIC_STATUS_SELECTOR = '[data-test="epic-status"]';

    use DatabaseMigrations;

    public function test_epic_can_be_created_updated_and_deleted_from_the_drawer(): void
    {
        $user = User::factory()->create();
        $suffix = Str::uuid()->toString();
        $project = Project::factory()->create(['name' => 'Dusk project '.$suffix]);
        $epicName = 'Dusk epic '.$suffix;
        $updatedEpicName = 'Updated epic '.$suffix;
        $epicId = null;

        $this->browse(function (Browser $browser) use (
            $user,
            $project,
            $epicName,
            $updatedEpicName,
            &$epicId,
        ): void {
            $browser->loginAs($user)
                ->visit('/epics')
                ->click('[data-test="epic-create-button"]')
                ->waitFor(self::EPIC_PROJECT_SELECTOR)
                ->click(self::EPIC_PROJECT_SELECTOR)
                ->waitUntil(
                    'document.querySelector(\'[data-test="epic-project"] option[value="'.$project->id.'"]\') !== null',
                    10,
                )
                ->select(self::EPIC_PROJECT_SELECTOR, (string) $project->id)
                ->type(self::EPIC_NAME_SELECTOR, $epicName)
                ->click('dialog[open] [data-test="epic-submit"]')
                ->waitFor(self::EPIC_STATUS_SELECTOR)
                ->assertSee($epicName);

            $epic = Epic::query()->where('name', $epicName)->firstOrFail();
            $epicId = $epic->id;

            $browser->click("[data-test='epic-edit-{$epic->id}']")
                ->waitFor(self::EPIC_NAME_SELECTOR)
                ->clear(self::EPIC_NAME_SELECTOR)
                ->type(self::EPIC_NAME_SELECTOR, $updatedEpicName)
                ->click('dialog[open] [data-test="epic-submit"]')
                ->waitFor(self::EPIC_STATUS_SELECTOR)
                ->assertSee($updatedEpicName)
                ->click("[data-test='epic-delete-{$epic->id}']")
                ->waitFor('dialog[open] [data-test="epic-confirm-submit"]')
                ->click('dialog[open] [data-test="epic-confirm-submit"]')
                ->waitFor(self::EPIC_STATUS_SELECTOR)
                ->assertDontSee($updatedEpicName);
        });

        $this->assertNotNull($epicId);
        $this->assertSoftDeleted('epics', ['id' => $epicId]);
    }
}
