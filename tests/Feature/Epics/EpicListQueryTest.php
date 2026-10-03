<?php

namespace Tests\Feature\Epics;

use Tests\TestCase;
use App\Models\Epic;
use App\Models\Project;
use App\Queries\Epics\EpicListQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EpicListQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_list_includes_epics_with_trashed_projects(): void
    {
        $project = Project::factory()->trashed()->create();
        $epic = Epic::factory()->for($project)->create();

        $epics = app(EpicListQuery::class)->active('');

        $this->assertSame([$epic->id], $epics->pluck('id')->all());
    }
}
