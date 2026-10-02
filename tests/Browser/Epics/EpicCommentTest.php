<?php

namespace Tests\Browser\Epics;

use App\Models\Epic;
use App\Models\User;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class EpicCommentTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_comment_added_from_the_edit_drawer_is_shown_after_reopening_it(): void
    {
        $user = User::factory()->create(['name' => 'Dusk commenter']);
        $epicName = 'Dusk epic '.Str::uuid()->toString();
        $epic = Epic::factory()->create(['name' => $epicName]);
        $commentBody = 'Dusk comment '.Str::uuid()->toString();

        $this->browse(function (Browser $browser) use ($user, $epic, $epicName, $commentBody): void {
            $browser->loginAs($user)
                ->visit('/epics?search='.urlencode($epicName))
                ->assertSeeIn('[data-test="epic-comments-count-'.$epic->id.'"]', '0')
                ->click('[data-test="epic-edit-'.$epic->id.'"]')
                ->waitFor('dialog[open] [data-test="epic-comments"]')
                ->assertSeeIn('dialog[open]', __('No comments yet.'))
                ->assertScript(
                    'document.querySelector(\'dialog[open] [data-test="epic-comment-body"]\').form.noValidate',
                    true,
                )
                ->type('dialog[open] [data-test="epic-comment-body"]', $commentBody)
                ->waitForReload(fn (Browser $browser) => $browser->click(
                    'dialog[open] [data-test="epic-comment-submit"]'
                ))
                ->waitFor('dialog[open] [data-test="epic-comment"]')
                ->assertInputValue('dialog[open] [data-test="epic-name"]', $epicName)
                ->assertSeeIn('dialog[open] [data-test="epic-comment"]', $commentBody)
                ->assertSeeIn('dialog[open] [data-test="epic-comment"]', 'Dusk commenter')
                ->assertSeeIn('[data-test="epic-comments-count-'.$epic->id.'"]', '1');
        });

        $this->assertDatabaseHas('epic_comments', [
            'epic_id' => $epic->id,
            'user_id' => $user->id,
            'body' => $commentBody,
        ]);
    }
}
