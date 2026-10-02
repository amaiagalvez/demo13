<?php

namespace Tests\Feature\Epics;

use Tests\TestCase;
use App\Models\Epic;
use App\Models\User;
use App\Models\EpicComment;
use App\Queries\Epics\EpicListQuery;
use Illuminate\Contracts\Support\Arrayable;
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

    public function test_list_embeds_only_the_most_recent_comments_of_each_epic_but_counts_all(): void
    {
        $this->actingAs(User::factory()->create());
        $limit = EpicListQuery::RECENT_COMMENTS_LIMIT;
        $epic = Epic::factory()->create();
        $otherEpic = Epic::factory()->create();
        EpicComment::factory()->for($epic)->create([
            'body' => 'Oldest hidden comment',
            'created_at' => now()->subDays(2),
        ]);
        EpicComment::factory()->count($limit)->for($epic)->create(['created_at' => now()->subDay()]);
        EpicComment::factory()->for($otherEpic)->create(['body' => 'Other epic comment']);

        $response = $this->get(route('epics.index'))->assertOk();

        $list = $response->viewData('list');

        if (! is_array($list)) {
            self::fail('The epics list view data must be an array.');
        }

        $rows = $list['rows'] ?? null;

        if (! is_iterable($rows) && ! $rows instanceof Arrayable) {
            self::fail('The epics list rows must be iterable.');
        }

        $row = collect($rows)->firstWhere('id', $epic->id);

        if (! is_array($row)) {
            self::fail('The epic row must be present in the list payload.');
        }

        $actions = $row['actions'] ?? null;

        if (! is_array($actions)) {
            self::fail('The epic row must embed its actions.');
        }

        $action = $actions[0] ?? null;

        if (! is_array($action)) {
            self::fail('The epic row must embed its edit action.');
        }

        $payload = $action['epic'] ?? null;

        if (! is_array($payload)) {
            self::fail('The edit action must embed the epic payload.');
        }

        $comments = $payload['comments'] ?? null;

        if (! is_array($comments)) {
            self::fail('The epic payload must embed its comments.');
        }

        $this->assertCount($limit, $comments);
        $this->assertSame($limit + 1, $payload['commentsCount']);
        $response->assertDontSee('Oldest hidden comment')
            ->assertSee('Other epic comment')
            ->assertSee('epic-comments-truncated');
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
}
