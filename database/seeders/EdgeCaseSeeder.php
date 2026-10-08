<?php

namespace Database\Seeders;

use App\Models\Epic;
use App\Models\Project;
use App\Models\Customer;
use Illuminate\Database\Seeder;

class EdgeCaseSeeder extends Seeder
{
    public function run(): void
    {
        // Customers without projects
        Customer::factory()->count(5)->sequence(
            fn ($sequence) => ['name' => "Customer without projects {$sequence->index}"]
        )->create();

        // Projects without epics (need a customer first)
        $customersForProjects = Customer::factory()->count(3)->create();

        foreach ($customersForProjects as $index => $customer) {
            Project::factory()->create([
                'name' => "Project without epics {$index}",
                'customer_id' => $customer->id,
            ]);
        }

        // Epics without comments (need a project first)
        $projectsForEpics = Project::factory()->count(3)->create();

        foreach ($projectsForEpics as $index => $project) {
            Epic::factory()->create([
                'name' => "Epic without comments {$index}",
                'project_id' => $project->id,
            ]);
        }
    }
}
