<?php

namespace Tests\Unit\Customers;

use App\Models\Customer;
use App\Queries\Customers\CustomerListQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerListQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_list_filters_out_deleted_customers(): void
    {
        $activeCustomer = Customer::query()->create(['name' => 'Active Customer']);
        $deletedCustomer = Customer::query()->create(['name' => 'Deleted Customer']);
        $deletedCustomer->delete();

        $customers = app(CustomerListQuery::class)->active('');

        $this->assertSame([$activeCustomer->id], $customers->pluck('id')->all());
    }

    public function test_active_list_is_ordered_by_name_ascending(): void
    {
        Customer::query()->create(['name' => 'Jon Bezeroa']);
        Customer::query()->create(['name' => 'Ane Bezeroa']);

        $customers = app(CustomerListQuery::class)->active('');

        $this->assertSame(['Ane Bezeroa', 'Jon Bezeroa'], $customers->pluck('name')->all());
    }

    public function test_trashed_list_only_contains_deleted_customers(): void
    {
        Customer::query()->create(['name' => 'Active Customer']);
        $deletedCustomer = Customer::query()->create(['name' => 'Deleted Customer']);
        $deletedCustomer->delete();

        $customers = app(CustomerListQuery::class)->trashed('');

        $this->assertSame([$deletedCustomer->id], $customers->pluck('id')->all());
    }

    public function test_trashed_list_shows_most_recently_deleted_customers_first(): void
    {
        $olderCustomer = Customer::query()->create(['name' => 'Older Customer']);
        $newerCustomer = Customer::query()->create(['name' => 'Newer Customer']);
        $olderCustomer->delete();
        $olderCustomer->forceFill(['deleted_at' => now()->subDay()])->saveQuietly();
        $newerCustomer->delete();

        $customers = app(CustomerListQuery::class)->trashed('');

        $this->assertSame(
            [$newerCustomer->id, $olderCustomer->id],
            $customers->pluck('id')->all(),
        );
    }

    public function test_lists_can_be_filtered_and_keep_the_search_in_pagination_urls(): void
    {
        Customer::query()->create(['name' => 'Ane Bezeroa']);
        Customer::query()->create(['name' => 'Jon Bezeroa']);

        $customers = app(CustomerListQuery::class)->active('Ane');

        $this->assertSame(['Ane Bezeroa'], $customers->pluck('name')->all());
        $this->assertStringContainsString('search=Ane', $customers->url(2));
    }

    public function test_lists_are_paginated_by_five_customers(): void
    {
        foreach (range(1, 6) as $number) {
            Customer::query()->create(['name' => "Customer {$number}"]);
        }

        $customers = app(CustomerListQuery::class)->active('');

        $this->assertSame(CustomerListQuery::PER_PAGE, $customers->perPage());
        $this->assertSame(2, $customers->lastPage());
    }
}
