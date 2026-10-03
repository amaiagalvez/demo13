<?php

namespace Tests\Feature\Projects;

use Tests\TestCase;
use App\Models\Project;
use App\Models\Customer;
use App\Queries\Projects\ProjectListQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectListQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_list_includes_projects_with_trashed_customers(): void
    {
        $customer = Customer::factory()->trashed()->create();
        $project = Project::factory()->for($customer)->create();

        $projects = app(ProjectListQuery::class)->active('');

        $this->assertSame([$project->id], $projects->pluck('id')->all());
    }
}
