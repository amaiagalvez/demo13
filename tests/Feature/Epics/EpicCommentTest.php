<?php

namespace Tests\Feature\Epics;

use Tests\TestCase;
use App\Models\Epic;
use App\Models\User;
use App\Models\EpicComment;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EpicCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_comment_on_an_epic_and_author_and_time_are_stored(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create(['name' => 'Ane Author']);
        $epic = Epic::factory()->create();

        $this->actingAs($user)
            ->from(route('epics.index'))
            ->post(route('epics.comments.store', $epic), ['body' => '  First comment  '])
            ->assertRedirect(route('epics.index'))
            ->assertSessionHas('status', __('Comment added successfully.'))
            ->assertSessionHas('commented_epic_id', $epic->id);

        $comment = EpicComment::query()->sole();
        $this->assertSame('First comment', $comment->body);
        $this->assertTrue($comment->epic->is($epic));
        $this->assertTrue($comment->user->is($user));
        $this->assertTrue($comment->created_at->equalTo(now()));
    }

    public function test_edit_payload_contains_comments_with_author_and_date(): void
    {
        $this->actingAs(User::factory()->create());
        $epic = Epic::factory()->create();
        EpicComment::factory()->for($epic)->for(User::factory()->state(['name' => 'Ane Author']))->create([
            'body' => 'Visible comment',
            'created_at' => '2026-10-01 09:30:00',
        ]);

        $this->get(route('epics.index'))
            ->assertOk()
            ->assertSee('Visible comment')
            ->assertSee('Ane Author')
            ->assertSee('2026-10-01 09:30');
    }

    public function test_comment_body_is_required(): void
    {
        $this->actingAs(User::factory()->create());
        $epic = Epic::factory()->create();

        $this->from(route('epics.index'))
            ->post(route('epics.comments.store', $epic), ['body' => '   '])
            ->assertRedirect(route('epics.index'))
            ->assertSessionHasErrorsIn('comment', ['body']);

        $this->assertDatabaseCount('epic_comments', 0);
    }

    public function test_deleted_epics_cannot_receive_comments(): void
    {
        $this->actingAs(User::factory()->create());
        $epic = Epic::factory()->trashed()->create();

        $this->post(route('epics.comments.store', $epic), ['body' => 'Late comment'])
            ->assertNotFound();

        $this->assertDatabaseCount('epic_comments', 0);
    }

    public function test_guests_cannot_comment(): void
    {
        $epic = Epic::factory()->create();

        $this->post(route('epics.comments.store', $epic), ['body' => 'Anonymous'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('epic_comments', 0);
    }

    public function test_comments_keep_existing_when_their_author_is_deleted(): void
    {
        $comment = EpicComment::factory()->create();

        $comment->user->delete();

        $this->assertNull($comment->fresh()->user_id);
    }
}
