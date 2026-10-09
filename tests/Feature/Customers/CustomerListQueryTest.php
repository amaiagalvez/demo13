<?php

namespace Tests\Feature\Customers;

use Tests\TestCase;
use App\Models\Customer;
use App\Queries\Customers\CustomerListQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CustomerListQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_counts_remain_unfiltered_when_the_active_list_is_searched(): void
    {
        Customer::factory()->create(['name' => 'Matching customer']);
        Customer::factory()->create(['name' => 'Other customer']);
        $query = app(CustomerListQuery::class);

        $customers = $query->active('Matching');
        $counts = $query->stateCounts();

        $this->assertSame(1, $customers->total());
        $this->assertSame(['active' => 2, 'archived' => 0, 'trashed' => 0], $counts);
    }

    public function test_active_list_filters_out_deleted_customers(): void
    {
        $activeCustomer = Customer::factory()->create();
        $deletedCustomer = Customer::factory()->trashed()->create();

        $customers = app(CustomerListQuery::class)->active('');

        $this->assertSame([$activeCustomer->id], $customers->pluck('id')->all());
    }

    public function test_active_list_is_ordered_by_name_ascending(): void
    {
        Customer::factory()->create(['name' => 'Jon Bezeroa']);
        Customer::factory()->create(['name' => 'Ane Bezeroa']);

        $customers = app(CustomerListQuery::class)->active('');

        $this->assertSame(['Ane Bezeroa', 'Jon Bezeroa'], $customers->pluck('name')->all());
    }

    public function test_trashed_list_only_contains_deleted_customers(): void
    {
        Customer::factory()->create();
        $deletedCustomer = Customer::factory()->trashed()->create();

        $customers = app(CustomerListQuery::class)->trashed('');

        $this->assertSame([$deletedCustomer->id], $customers->pluck('id')->all());
    }

    public function test_trashed_list_shows_most_recently_deleted_customers_first(): void
    {
        $this->travelTo('2026-10-02 12:00:00');
        $olderCustomer = Customer::factory()->trashed()->create();
        $newerCustomer = Customer::factory()->trashed()->create();
        $tiedCustomer = Customer::factory()->trashed()->create();
        $olderCustomer->forceFill(['deleted_at' => now()->subHour()])->saveQuietly();

        $customers = app(CustomerListQuery::class)->trashed('');

        $this->assertSame(
            [$newerCustomer->id, $tiedCustomer->id, $olderCustomer->id],
            $customers->pluck('id')->all(),
        );
    }
}
