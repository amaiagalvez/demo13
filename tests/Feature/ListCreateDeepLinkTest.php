<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Epic;
use App\Models\User;
use App\Models\Project;
use App\Models\Customer;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Every list accepts ?create=1 to open its drawer straight from a deep link, which is how the
 * "no active projects" callout sends the user to the project form. The epic list was missing the
 * branch, so the link worked for two resources out of three and nothing pinned it.
 */
class ListCreateDeepLinkTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: non-empty-string, 1: non-empty-string, 2: non-empty-string}>
     */
    public static function resources(): array
    {
        return [
            'customers' => ['customers.index', 'customer-form', 'New customer'],
            'projects' => ['projects.index', 'project-form', 'New project'],
            'epics' => ['epics.index', 'epic-form', 'New epic'],
        ];
    }

    /**
     * The drawer is opened by an x-init on the list, so the response has to carry the create branch.
     *
     * @param  non-empty-string  $indexRoute
     * @param  non-empty-string  $formName
     * @param  non-empty-string  $heading
     */
    #[DataProvider('resources')]
    public function test_the_create_flag_opens_the_drawer(string $indexRoute, string $formName, string $heading): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route($indexRoute, ['create' => 1]))
            ->assertOk()
            ->assertSee("modal-show', { name: '{$formName}'", escape: false)
            ->assertSee($heading, escape: false);
    }

    /**
     * The flag drives an Alpine x-init, so it only works where the list can actually create: a
     * customer always can, a project needs an active customer and an epic an active project.
     *
     * @param  non-empty-string  $indexRoute
     * @param  non-empty-string  $formName
     * @param  non-empty-string  $heading
     */
    #[DataProvider('resources')]
    public function test_the_drawer_opens_once_the_parent_exists(string $indexRoute, string $formName, string $heading): void
    {
        $customer = Customer::factory()->create();
        Project::factory()->for($customer)->create();
        Epic::factory()->create(['project_id' => Project::first()->id]);

        $this->actingAs(User::factory()->create())
            ->get(route($indexRoute, ['create' => 1]))
            ->assertOk()
            ->assertSee("modal-show', { name: '{$formName}'", escape: false);
    }
}
