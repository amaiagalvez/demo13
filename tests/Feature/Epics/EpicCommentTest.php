<?php

namespace Tests\Feature\Epics;

use Tests\TestCase;
use App\Models\Epic;
use App\Models\User;
use App\Models\EpicComment;
use App\Queries\Epics\EpicListQuery;
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

        $commentEpic = $comment->epic;
        $commentAuthor = $comment->user;
        $commentCreatedAt = $comment->created_at;

        if ($commentEpic === null || $commentAuthor === null || $commentCreatedAt === null) {
            self::fail('The comment must reference its epic, its author and its creation time.');
        }

        $this->assertTrue($commentEpic->is($epic));
        $this->assertTrue($commentAuthor->is($user));
        $this->assertTrue($commentCreatedAt->equalTo(now()));
    }

    public function test_epic_comments_are_loaded_on_demand_with_author_and_date(): void
    {
        $this->actingAs(User::factory()->create());
        $epic = Epic::factory()->create();
        EpicComment::factory()->for($epic)->for(User::factory()->state(['name' => 'Ane Author']))->create([
            'body' => 'Visible comment',
            'created_at' => '2026-10-01 09:30:00',
        ]);

        $this->get(route('epics.index'))
            ->assertOk()
            ->assertDontSee('Visible comment')
            ->assertDontSee('Ane Author');

        $this->getJson(route('epics.comments.index', $epic))
            ->assertOk()
            ->assertJsonPath('comments.0.body', 'Visible comment')
            ->assertJsonPath('comments.0.author', 'Ane Author')
            ->assertJsonPath('comments.0.dateTime', '2026-10-01T09:30:00+00:00');
    }

    public function test_comment_endpoint_shows_deleted_user_for_comment_without_author(): void
    {
        $this->actingAs(User::factory()->create());
        $epic = Epic::factory()->create();
        EpicComment::factory()->for($epic)->withoutAuthor()->create(['body' => 'Orphaned comment']);

        $this->getJson(route('epics.comments.index', $epic))
            ->assertOk()
            ->assertJsonPath('comments.0.author', __('Deleted user'))
            ->assertJsonPath('comments.0.body', 'Orphaned comment');
    }

    public function test_comment_endpoint_returns_only_the_latest_comments_for_the_requested_epic(): void
    {
        $this->actingAs(User::factory()->create());
        $limit = EpicListQuery::RECENT_COMMENTS_LIMIT;
        $epic = Epic::factory()->create();
        $otherEpic = Epic::factory()->create();

        EpicComment::factory()->for($epic)->create([
            'body' => 'Oldest hidden comment',
            'created_at' => now()->subDays(2),
        ]);

        foreach (range(1, $limit) as $index) {
            EpicComment::factory()->for($epic)->create([
                'body' => 'Recent comment '.$index,
                'created_at' => now()->subMinutes($limit - $index),
            ]);
        }

        EpicComment::factory()->for($otherEpic)->create(['body' => 'Other epic comment']);

        $this->get(route('epics.index'))
            ->assertOk()
            ->assertDontSee('Oldest hidden comment')
            ->assertDontSee('Recent comment 1')
            ->assertDontSee('Other epic comment')
            ->assertSee('epic-comments-count-'.$epic->id, false);

        $this->getJson(route('epics.comments.index', $epic))
            ->assertOk()
            ->assertJsonCount($limit, 'comments')
            ->assertJsonMissing(['body' => 'Oldest hidden comment'])
            ->assertJsonMissing(['body' => 'Other epic comment'])
            ->assertJsonPath('comments.0.body', 'Recent comment '.$limit)
            ->assertJsonPath('comments.'.($limit - 1).'.body', 'Recent comment 1');
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

    public function test_comment_body_accepts_5000_characters_over_http(): void
    {
        $this->actingAs(User::factory()->create());
        $epic = Epic::factory()->create();
        $body = str_repeat('x', 5000);

        $this->from(route('epics.index'))
            ->post(route('epics.comments.store', $epic), ['body' => $body])
            ->assertRedirect(route('epics.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('epic_comments', ['epic_id' => $epic->id, 'body' => $body]);
    }

    public function test_comment_body_rejects_5001_characters_over_http(): void
    {
        $this->actingAs(User::factory()->create());
        $epic = Epic::factory()->create();

        $this->from(route('epics.index'))
            ->post(route('epics.comments.store', $epic), ['body' => str_repeat('x', 5001)])
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

    public function test_guests_cannot_load_epic_comments(): void
    {
        $epic = Epic::factory()->create();

        $this->get(route('epics.comments.index', $epic))
            ->assertRedirect(route('login'));
    }

    public function test_trashed_epics_do_not_expose_their_comments(): void
    {
        $this->actingAs(User::factory()->create());
        $epic = Epic::factory()->trashed()->create();

        $this->getJson(route('epics.comments.index', $epic))
            ->assertNotFound();
    }

    public function test_comments_keep_existing_when_their_author_is_deleted(): void
    {
        $comment = EpicComment::factory()->create();
        $commentAuthor = $comment->user;

        if ($commentAuthor === null) {
            self::fail('The comment must have an author.');
        }

        $commentAuthor->delete();

        $freshComment = $comment->fresh();

        if ($freshComment === null) {
            self::fail('The comment must still exist after its author is deleted.');
        }

        $this->assertNull($freshComment->user_id);
    }

    public function test_comments_can_resolve_their_epic_after_it_is_trashed(): void
    {
        $epic = Epic::factory()->create();
        $comment = EpicComment::factory()->for($epic)->create();

        $epic->delete();
        $freshComment = $comment->fresh();

        if ($freshComment === null) {
            self::fail('The comment must still exist after its epic is trashed.');
        }

        $commentEpic = $freshComment->epic;

        if ($commentEpic === null) {
            self::fail('The comment must still resolve its trashed epic.');
        }

        $this->assertTrue($commentEpic->is($epic));
    }
}
