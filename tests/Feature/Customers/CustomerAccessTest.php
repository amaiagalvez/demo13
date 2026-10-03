<?php

namespace Tests\Feature\Customers;

use Tests\TestCase;
use App\Models\User;

class CustomerAccessTest extends TestCase
{
    public function test_guests_are_redirected_to_the_customers_login_page(): void
    {
        $this->get(route('customers.index'))->assertRedirect(route('login'));
    }

    public function test_unverified_users_are_redirected_to_email_verification(): void
    {
        $this->actingAs(User::factory()->unverified()->make()->forceFill(['id' => 1]))
            ->get(route('customers.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_invalid_search_input_is_rejected(): void
    {
        $this->actingAs(User::factory()->make()->forceFill(['id' => 1]))
            ->get(route('customers.index', ['search' => ['Ane']]))
            ->assertSessionHasErrors(['search']);
    }

    public function test_guests_cannot_access_the_customer_trash(): void
    {
        $this->get(route('customers.trash.index'))->assertRedirect(route('login'));
    }
}
