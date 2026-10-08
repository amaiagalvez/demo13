<?php

namespace Tests\Feature\Epics;

use Tests\TestCase;
use App\Models\Epic;
use App\Models\Project;
use App\Queries\Epics\EpicListQuery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

class EpicListQueryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_active_list_includes_epics_with_trashed_projects(): void
    {
        $project = Project::factory()->trashed()->create();
        $epic = Epic::factory()->for($project)->create();

        $epics = app(EpicListQuery::class)->active('');

        $this->assertSame([$epic->id], $epics->pluck('id')->all());

        $listedEpic = $epics->first();

        if ($listedEpic === null) {
            self::fail('The active list must include the epic with its trashed project.');
        }

        $this->assertTrue($listedEpic->project->is($project));
    }
}
