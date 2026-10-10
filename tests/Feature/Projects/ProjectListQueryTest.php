<?php

namespace Tests\Feature\Projects;

use Tests\TestCase;
use Projects13\Models\Project;
use Customers13\Models\Customer;
use Projects13\Queries\Projects\ProjectListQuery;
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

        $listedProject = $projects->first();

        if ($listedProject === null) {
            self::fail('The active list must include the project with its trashed customer.');
        }

        $this->assertTrue($listedProject->customer->is($customer));
    }
}
