<?php

namespace Tests\Feature\Customers;

use Tests\TestCase;
use App\Models\Customer;
use App\Queries\ListQueryBase;
use Illuminate\Support\Facades\DB;
use App\Queries\Customers\CustomerListQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CustomerListQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_counts_reuse_the_unfiltered_active_total_without_a_duplicate_query(): void
    {
        Customer::factory()->count(2)->create();
        Customer::factory()->inactive()->create();
        Customer::factory()->trashed()->create();
        $query = app(CustomerListQuery::class);
        DB::enableQueryLog();
        DB::flushQueryLog();

        try {
            $customers = $query->active('');
            $counts = $query->stateCounts(activeTotal: $customers->total());
            $executedQueries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $this->assertSame(['active' => 2, 'inactive' => 1, 'trashed' => 1], $counts);
        $this->assertCount(4, $executedQueries);
    }

    public function test_state_counts_reuse_an_empty_active_total_without_a_duplicate_query(): void
    {
        $query = app(CustomerListQuery::class);
        DB::enableQueryLog();
        DB::flushQueryLog();

        try {
            $counts = $query->stateCounts(activeTotal: 0);
            $executedQueries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $this->assertSame(['active' => 0, 'inactive' => 0, 'trashed' => 0], $counts);
        $this->assertCount(2, $executedQueries);
    }

    public function test_state_counts_reuse_the_unfiltered_inactive_total_without_a_duplicate_query(): void
    {
        Customer::factory()->create();
        Customer::factory()->count(2)->inactive()->create();
        Customer::factory()->trashed()->create();
        $query = app(CustomerListQuery::class);
        DB::enableQueryLog();
        DB::flushQueryLog();

        try {
            $customers = $query->inactive('');
            $counts = $query->stateCounts(inactiveTotal: $customers->total());
            $executedQueries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $this->assertSame(['active' => 1, 'inactive' => 2, 'trashed' => 1], $counts);
        $this->assertCount(4, $executedQueries);
    }

    public function test_state_counts_reuse_an_empty_inactive_total_without_a_duplicate_query(): void
    {
        $query = app(CustomerListQuery::class);
        DB::enableQueryLog();
        DB::flushQueryLog();

        try {
            $counts = $query->stateCounts(inactiveTotal: 0);
            $executedQueries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $this->assertSame(['active' => 0, 'inactive' => 0, 'trashed' => 0], $counts);
        $this->assertCount(2, $executedQueries);
    }

    public function test_state_counts_remain_unfiltered_when_the_active_list_is_searched(): void
    {
        Customer::factory()->create(['name' => 'Matching customer']);
        Customer::factory()->create(['name' => 'Other customer']);
        $query = app(CustomerListQuery::class);

        $customers = $query->active('Matching');
        $counts = $query->stateCounts();

        $this->assertSame(1, $customers->total());
        $this->assertSame(['active' => 2, 'inactive' => 0, 'trashed' => 0], $counts);
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

    public function test_lists_can_be_filtered_and_keep_the_search_in_pagination_urls(): void
    {
        Customer::factory()->create(['name' => 'Ane Bezeroa']);
        Customer::factory()->create(['name' => 'Jon Bezeroa']);

        $customers = app(CustomerListQuery::class)->active('Ane');

        $this->assertSame(['Ane Bezeroa'], $customers->pluck('name')->all());
        $this->assertStringContainsString('search=Ane', $customers->url(2));
    }

    public function test_search_treats_percent_as_a_literal_character(): void
    {
        Customer::factory()->create(['name' => 'Customer 100%']);
        Customer::factory()->create(['name' => 'Customer 1000']);

        $customers = app(CustomerListQuery::class)->active('%');

        $this->assertSame(['Customer 100%'], $customers->pluck('name')->all());
    }

    public function test_search_treats_underscore_as_a_literal_character(): void
    {
        Customer::factory()->create(['name' => 'Ane_One']);
        Customer::factory()->create(['name' => 'AneXOne']);

        $customers = app(CustomerListQuery::class)->active('_');

        $this->assertSame(['Ane_One'], $customers->pluck('name')->all());
    }

    public function test_search_treats_backslash_as_a_literal_character(): void
    {
        Customer::factory()->create(['name' => 'Ane\\One']);
        Customer::factory()->create(['name' => 'AneOne']);

        $customers = app(CustomerListQuery::class)->active('\\');

        $this->assertSame(['Ane\\One'], $customers->pluck('name')->all());
    }

    public function test_lists_are_paginated_by_the_shared_page_size(): void
    {
        for ($index = 1; $index <= ListQueryBase::PER_PAGE + 1; $index++) {
            Customer::factory()->create();
        }

        $customers = app(CustomerListQuery::class)->active('');

        $this->assertSame(ListQueryBase::PER_PAGE, $customers->perPage());
        $this->assertSame(ListQueryBase::PER_PAGE + 1, $customers->total());
        $this->assertSame(2, $customers->lastPage());
    }

    public function test_trashed_lists_are_paginated_by_the_shared_page_size(): void
    {
        for ($index = 1; $index <= ListQueryBase::PER_PAGE + 1; $index++) {
            Customer::factory()->trashed()->create();
        }

        $customers = app(CustomerListQuery::class)->trashed('');

        $this->assertSame(ListQueryBase::PER_PAGE, $customers->perPage());
        $this->assertSame(ListQueryBase::PER_PAGE + 1, $customers->total());
        $this->assertSame(2, $customers->lastPage());
    }
}
