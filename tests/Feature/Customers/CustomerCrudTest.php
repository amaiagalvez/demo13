<?php

namespace Tests\Feature\Customers;

use Tests\TestCase;
use App\Models\Epic;
use App\Models\User;
use App\Models\Project;
use App\Models\Customer;
use App\Models\EpicComment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\QueryException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CustomerCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_create_update_and_delete_customers(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('customers.store'), ['name' => 'Ane Bezeroa'])
            ->assertRedirect(route('customers.index'));

        $customer = Customer::query()->firstOrFail();
        $this->assertSame('Ane Bezeroa', $customer->name);

        $this->get(route('customers.index'))
            ->assertOk()
            ->assertSee('role="status"', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee('customer-create')
            ->assertSee('aria-labelledby="customer-form-heading"', false)
            ->assertSee('id="customer-form-heading"', false)
            ->assertSee('aria-labelledby="customer-confirm-heading"', false)
            ->assertSee('id="customer-confirm-heading"', false)
            ->assertSee('customer-edit-' . $customer->id);

        $this->put(route('customers.update', $customer), ['name' => 'Jon Bezeroa'])
            ->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Jon Bezeroa']);

        $this->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));
        $this->assertSoftDeleted($customer);
    }

    public function test_customer_with_a_project_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $project = Project::factory()->for($customer)->create();

        $this->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('error', __('Customer cannot be deleted while it has projects.'));

        $this->assertNotSoftDeleted($customer);
        $this->assertModelExists($project);
    }

    public function test_customer_creation_is_forbidden_when_the_gate_denies_it(): void
    {
        $this->actingAs(User::factory()->create());
        Gate::before(static fn(User $user, string $ability): bool => false);

        $this->post(route('customers.store'), ['name' => 'Forbidden customer'])
            ->assertForbidden();

        $this->assertDatabaseMissing('customers', ['name' => 'Forbidden customer']);
    }

    public function test_customer_model_cannot_be_deleted_while_it_has_projects(): void
    {
        $customer = Customer::factory()->create();
        $project = Project::factory()->for($customer)->create();

        $this->assertFalse($customer->delete());

        $this->assertNotSoftDeleted($customer);
        $this->assertModelExists($project);
    }

    public function test_customer_list_shows_the_number_of_active_projects_epics_and_comments(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $project = Project::factory()->for($customer)->create();
        Project::factory()->for($customer)->create();
        Epic::factory()->for($project)->create();
        $commentedEpic = Epic::factory()->for($project)->create();
        EpicComment::factory()->count(5)->for($commentedEpic)->create();
        $inactiveProjectEpic = Epic::factory()->for(Project::factory()->for($customer)->inactive())->create();
        EpicComment::factory()->count(4)->for($inactiveProjectEpic)->create();
        EpicComment::factory()->count(6)->for(Epic::factory()->for($project)->inactive())->create();
        EpicComment::factory()->count(7)->for(Epic::factory()->for($project)->trashed())->create();

        $this->get(route('customers.index'))
            ->assertOk()
            ->assertSeeInOrder(['customer-projects-count-' . $customer->id, '2'])
            ->assertSeeInOrder(['customer-epics-count-' . $customer->id, '3'])
            ->assertSeeInOrder(['customer-comments-count-' . $customer->id, '5']);
    }

    public function test_customer_with_a_trashed_project_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $project = Project::factory()->for($customer)->trashed()->create();

        $this->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('error', __('Customer cannot be deleted while it has projects.'));

        $this->assertNotSoftDeleted($customer);
        $this->assertSoftDeleted($project);
    }

    public function test_customer_store_returns_created_customer_as_json(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson(route('customers.store'), ['name' => 'Select2 Customer'])
            ->assertCreated()
            ->assertJsonPath('name', 'Select2 Customer');

        $this->assertDatabaseHas('customers', ['name' => 'Select2 Customer']);
    }

    public function test_json_customer_store_reports_deleted_name_conflicts(): void
    {
        $this->actingAs(User::factory()->create());
        Customer::factory()->trashed()->create(['name' => 'Deleted Select2 Customer']);

        $this->postJson(route('customers.store'), ['name' => 'Deleted Select2 Customer'])
            ->assertStatus(409)
            ->assertJsonPath('errors.name.0', __('A deleted customer already uses the name :name.', ['name' => 'Deleted Select2 Customer']));

        $this->assertDatabaseCount('customers', 1);
    }

    public function test_customer_name_is_required(): void
    {
        $this->actingAs(User::factory()->create());

        $this->from(route('customers.index'))
            ->post(route('customers.store'), [
                '_customer_form' => 'create',
                'name' => '',
            ])
            ->assertRedirect(route('customers.index'))
            ->assertSessionHasErrors(['name'])
            ->assertSessionHasInput('_customer_form', 'create');
    }

    public function test_customer_name_shorter_than_four_characters_displays_validation_error(): void
    {
        $this->actingAs(User::factory()->create());

        $this->followingRedirects()
            ->from(route('customers.index'))
            ->post(route('customers.store'), [
                '_customer_form' => 'create',
                'name' => 'Abc',
            ])
            ->assertOk()
            ->assertSee(__('validation.min.string', ['attribute' => 'name', 'min' => 4]));

        $this->assertDatabaseMissing('customers', ['name' => 'Abc']);
    }

    public function test_customer_name_cannot_exceed_255_characters_over_http(): void
    {
        $this->actingAs(User::factory()->create());

        $this->from(route('customers.index'))
            ->post(route('customers.store'), ['name' => str_repeat('a', 256)])
            ->assertRedirect(route('customers.index'))
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_customer_name_must_be_a_string_over_http(): void
    {
        $this->actingAs(User::factory()->create());

        $this->from(route('customers.index'))
            ->post(route('customers.store'), ['name' => ['Ane Bezeroa']])
            ->assertRedirect(route('customers.index'))
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_store_rejects_an_active_duplicate_name(): void
    {
        $this->actingAs(User::factory()->create());
        Customer::factory()->create(['name' => 'Existing Customer']);

        $this->from(route('customers.index'))
            ->post(route('customers.store'), ['name' => 'Existing Customer'])
            ->assertRedirect(route('customers.index'))
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseCount('customers', 1);
    }

    public function test_store_trims_name_before_validation_and_saving(): void
    {
        $this->actingAs(User::factory()->create());
        Customer::factory()->create(['name' => 'Ane Bezeroa']);

        $this->from(route('customers.index'))
            ->post(route('customers.store'), ['name' => '  Ane Bezeroa  '])
            ->assertRedirect(route('customers.index'))
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseCount('customers', 1);
    }

    public function test_store_saves_name_without_surrounding_whitespace(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('customers.store'), ['name' => '  Ane Bezeroa  '])
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', ['name' => 'Ane Bezeroa']);
    }

    public function test_store_accepts_a_255_character_unicode_name_over_http(): void
    {
        $this->actingAs(User::factory()->create());
        $name = str_repeat('x', 252) . 'é中😀';

        $this->post(route('customers.store'), ['name' => $name])
            ->assertRedirect(route('customers.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customers', ['name' => $name]);
    }

    public function test_store_converts_a_concurrent_duplicate_insert_to_validation_error(): void
    {
        $this->actingAs(User::factory()->create());
        $name = 'Concurrent Store Customer';
        $this->insertCustomerAfterNameUniquenessCheck($name);

        $this->from(route('customers.index'))
            ->post(route('customers.store'), ['name' => $name])
            ->assertRedirect(route('customers.index'))
            ->assertSessionHasErrors([
                'name' => __('validation.unique', ['attribute' => __('Name')]),
            ]);

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('customers', ['name' => $name, 'deleted_at' => null]);
    }

    public function test_update_converts_a_concurrent_duplicate_insert_to_validation_error(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create(['name' => 'Original Customer']);
        $name = 'Concurrent Update Customer';
        $this->insertCustomerAfterNameUniquenessCheck($name);

        $this->from(route('customers.index'))
            ->put(route('customers.update', $customer), ['name' => $name])
            ->assertRedirect(route('customers.index'))
            ->assertSessionHasErrors([
                'name' => __('validation.unique', ['attribute' => __('Name')]),
            ]);

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Original Customer']);
        $this->assertDatabaseHas('customers', ['name' => $name, 'deleted_at' => null]);
    }

    public function test_database_rejects_duplicate_customer_names(): void
    {
        Customer::factory()->create(['name' => 'Ane Bezeroa']);

        $this->expectException(QueryException::class);

        Customer::factory()->create(['name' => 'Ane Bezeroa']);
    }

    public function test_customer_name_can_be_reused_after_soft_delete(): void
    {
        $this->actingAs(User::factory()->create());
        $deletedCustomer = Customer::factory()->trashed()->create(['name' => 'Ane Bezeroa']);

        $this->post(route('customers.store'), ['name' => 'Ane Bezeroa'])
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('deleted_customer_conflict', [
                'id' => $deletedCustomer->id,
                'name' => 'Ane Bezeroa',
            ]);

        $this->get(route('customers.index'))
            ->assertOk()
            ->assertSee('customer-conflict-create-new')
            ->assertSee('customer-conflict-restore')
            ->assertSee('aria-labelledby="customer-name-conflict-heading"', false)
            ->assertSee('id="customer-name-conflict-heading"', false)
            ->assertSee(__('Customer name already in trash'));

        $this->assertDatabaseCount('customers', 1);

        $this->post(route('customers.store'), [
            'name' => 'Ane Bezeroa',
            'reuse_deleted_name' => '1',
        ])->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', [
            'name' => 'Ane Bezeroa',
            'deleted_at' => null,
        ]);
        $this->assertSoftDeleted($deletedCustomer);
    }

    public function test_failed_update_keeps_the_selected_customer_context(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();

        $this->from(route('customers.index'))
            ->put(route('customers.update', $customer), [
                '_customer_form' => 'edit-' . $customer->id,
                '_customer_id' => $customer->id,
                'name' => '',
            ])
            ->assertRedirect(route('customers.index'))
            ->assertSessionHasErrors(['name'])
            ->assertSessionHasInput('_customer_form', 'edit-' . $customer->id)
            ->assertSessionHasInput('_customer_id', (string) $customer->id);
    }

    public function test_update_rejects_a_duplicate_customer_name(): void
    {
        $this->actingAs(User::factory()->create());
        Customer::factory()->create(['name' => 'Existing Customer']);
        $customerToUpdate = Customer::factory()->create(['name' => 'Customer to update']);

        $this->from(route('customers.index'))
            ->put(route('customers.update', $customerToUpdate), ['name' => 'Existing Customer'])
            ->assertRedirect(route('customers.index'))
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseHas('customers', [
            'id' => $customerToUpdate->id,
            'name' => 'Customer to update',
        ]);
    }

    public function test_update_rejects_a_customer_name_shorter_than_four_characters(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create(['name' => 'Existing Customer']);

        $this->from(route('customers.index'))
            ->put(route('customers.update', $customer), ['name' => 'Abc'])
            ->assertRedirect(route('customers.index'))
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Existing Customer',
        ]);
    }

    public function test_update_saves_name_without_surrounding_whitespace(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create(['name' => 'Original Customer']);

        $this->put(route('customers.update', $customer), ['name' => '  Ane Bezeroa  '])
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Ane Bezeroa']);
    }

    public function test_only_validated_attributes_are_persisted(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('customers.store'), [
            'name' => 'Ane Bezeroa',
            'created_at' => '2000-01-01 00:00:00',
        ]);

        $this->assertDatabaseMissing('customers', [
            'name' => 'Ane Bezeroa',
            'created_at' => '2000-01-01 00:00:00',
        ]);
    }

    public function test_customers_are_paginated(): void
    {
        $this->actingAs(User::factory()->create());

        Customer::factory()->create(['name' => 'Aardvark customer']);

        foreach (range(2, 16) as $number) {
            Customer::factory()->create(['name' => sprintf('Customer %02d', $number)]);
        }

        $this->get(route('customers.index'))
            ->assertOk()
            ->assertSee('customer-pagination')
            ->assertSee('Aardvark customer')
            ->assertDontSee('Customer 16');
    }

    public function test_customers_can_be_searched_by_visible_data(): void
    {
        $this->actingAs(User::factory()->create());
        Customer::factory()->create(['name' => 'Ane Bezeroa']);
        Customer::factory()->create(['name' => 'Jon Bezeroa']);

        $this->get(route('customers.index', ['search' => 'Ane']))
            ->assertOk()
            ->assertSee('Ane Bezeroa')
            ->assertDontSee('Jon Bezeroa');
    }

    public function test_customer_search_is_preserved_when_paginating(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (range(1, 16) as $number) {
            Customer::factory()->create(['name' => "Searchable Customer {$number}"]);
        }

        $this->get(route('customers.index', ['search' => 'Searchable']))
            ->assertOk()
            ->assertSee('search=Searchable', false);
    }

    public function test_empty_customer_search_returns_all_customers(): void
    {
        $this->actingAs(User::factory()->create());
        Customer::factory()->create(['name' => 'Ane Bezeroa']);
        Customer::factory()->create(['name' => 'Jon Bezeroa']);

        $this->get(route('customers.index', ['search' => '']))
            ->assertOk()
            ->assertSee('Ane Bezeroa')
            ->assertSee('Jon Bezeroa');
    }

    public function test_customer_list_returns_an_empty_result_for_a_page_beyond_the_last_page(): void
    {
        $this->actingAs(User::factory()->create());
        Customer::factory()->count(6)->create();

        $this->get(route('customers.index', ['page' => 999]))
            ->assertOk()
            ->assertViewHas(
                'customers',
                static fn(LengthAwarePaginator $customers): bool => $customers->currentPage() === 999
                    && $customers->count() === 0
                    && $customers->total() === 6
            );
    }

    private function insertCustomerAfterNameUniquenessCheck(string $name): void
    {
        $competitorCreated = false;

        DB::listen(static function (QueryExecuted $query) use ($name, &$competitorCreated): void {
            if (
                $competitorCreated
                || ! str_starts_with(strtolower(ltrim($query->sql)), 'select')
                || ! str_contains(strtolower($query->sql), 'customers')
                || ! in_array($name, $query->bindings, true)
            ) {
                return;
            }

            $competitorCreated = true;
            Customer::factory()->create(['name' => $name]);
        });
    }
}
