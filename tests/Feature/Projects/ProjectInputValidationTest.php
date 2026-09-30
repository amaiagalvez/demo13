<?php

namespace Tests\Feature\Projects;

use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectInputValidationTest extends TestCase
{
    use RefreshDatabase;

    private const START_DATE = '2026-10-01';

    private const OPEN_ENDED_PROJECT = 'Open-ended project';

    public function test_name_start_date_and_customer_are_required(): void
    {
        $this->actingAs(User::factory()->create());

        $this->from(route('projects.index'))
            ->post(route('projects.store'), [])
            ->assertRedirect(route('projects.index'))
            ->assertSessionHasErrors(['name', 'start_date', 'customer_id']);
    }

    public function test_project_name_must_have_more_than_three_characters(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();

        $this->followingRedirects()
            ->from(route('projects.index'))
            ->post(route('projects.store'), [
                'name' => 'Abc',
                'start_date' => self::START_DATE,
                'customer_id' => $customer->id,
            ])
            ->assertSee(__('validation.min.string', ['attribute' => 'name', 'min' => 4]));

        $this->assertDatabaseMissing('projects', ['name' => 'Abc']);
    }

    public function test_project_can_be_created_without_an_end_date(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();

        $this->post(route('projects.store'), [
            'name' => self::OPEN_ENDED_PROJECT,
            'start_date' => self::START_DATE,
            'end_date' => '',
            'customer_id' => $customer->id,
        ])->assertRedirect(route('projects.index'));

        $this->assertDatabaseHas('projects', [
            'name' => self::OPEN_ENDED_PROJECT,
            'start_date' => self::START_DATE,
            'end_date' => null,
            'customer_id' => $customer->id,
        ]);

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSee(self::OPEN_ENDED_PROJECT);
    }

    public function test_end_date_can_equal_start_date(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();

        $this->post(route('projects.store'), [
            'name' => 'One-day project',
            'start_date' => self::START_DATE,
            'end_date' => self::START_DATE,
            'customer_id' => $customer->id,
        ])->assertRedirect(route('projects.index'));

        $this->assertDatabaseHas('projects', [
            'name' => 'One-day project',
            'start_date' => self::START_DATE,
            'end_date' => self::START_DATE,
        ]);
    }

    public function test_end_date_cannot_precede_start_date(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();

        $this->from(route('projects.index'))
            ->post(route('projects.store'), [
                'name' => 'Invalid date range',
                'start_date' => '2026-12-01',
                'end_date' => '2026-11-30',
                'customer_id' => $customer->id,
            ])
            ->assertRedirect(route('projects.index'))
            ->assertSessionHasErrors(['end_date']);
    }

    public function test_trashed_customers_cannot_be_assigned_to_projects(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->trashed()->create();

        $this->from(route('projects.index'))
            ->post(route('projects.store'), [
                'name' => 'Project without an active customer',
                'start_date' => self::START_DATE,
                'end_date' => '2026-10-31',
                'customer_id' => $customer->id,
            ])
            ->assertRedirect(route('projects.index'))
            ->assertSessionHasErrors(['customer_id']);
    }
}
