<?php

namespace Tests\Unit\Queries;

use Tests\TestCase;
use App\Queries\Customers\CustomerListQuery;

class CustomerListQueryTest extends TestCase
{
    private CustomerListQuery $query;

    protected function setUp(): void
    {
        parent::setUp();
        $this->query = new CustomerListQuery;
    }

    public function test_active_method_exists(): void
    {
        $reflection = new \ReflectionClass($this->query);
        $this->assertTrue($reflection->hasMethod('active'));
    }

    public function test_archived_method_exists(): void
    {
        $reflection = new \ReflectionClass($this->query);
        $this->assertTrue($reflection->hasMethod('archived'));
    }

    public function test_trashed_method_exists(): void
    {
        $reflection = new \ReflectionClass($this->query);
        $this->assertTrue($reflection->hasMethod('trashed'));
    }

    public function test_find_trashed_by_name_method_exists(): void
    {
        $reflection = new \ReflectionClass($this->query);
        $this->assertTrue($reflection->hasMethod('findTrashedByName'));
    }

    public function test_state_counts_method_exists(): void
    {
        $reflection = new \ReflectionClass($this->query);
        $this->assertTrue($reflection->hasMethod('stateCounts'));
    }

    public function test_state_counts_returns_array_with_expected_keys(): void
    {
        $result = $this->query->stateCounts(10, 5, 3);

        $this->assertSame(
            ['active' => 10, 'inactive' => 5, 'trashed' => 3],
            $result,
        );
    }

    public function test_with_active_counts_method_exists(): void
    {
        $reflection = new \ReflectionClass($this->query);
        $this->assertTrue($reflection->hasMethod('withActiveCounts'));
        $this->assertTrue($reflection->getMethod('withActiveCounts')->isPrivate());
    }

    public function test_active_comments_count_method_exists(): void
    {
        $reflection = new \ReflectionClass($this->query);
        $this->assertTrue($reflection->hasMethod('activeCommentsCount'));
        $this->assertTrue($reflection->getMethod('activeCommentsCount')->isPrivate());
    }
}
