<?php

namespace Tests\Unit\Queries;

use Tests\TestCase;
use App\Queries\Projects\ProjectListQuery;

class ProjectListQueryTest extends TestCase
{
    private ProjectListQuery $query;

    protected function setUp(): void
    {
        parent::setUp();
        $this->query = new ProjectListQuery;
    }

    public function test_search_columns_constant_exists(): void
    {
        $reflection = new \ReflectionClass($this->query);
        $this->assertTrue($reflection->hasConstant('SEARCH_COLUMNS'));
        $columns = $reflection->getConstant('SEARCH_COLUMNS');
        $this->assertIsArray($columns);
        $this->assertContains('projects.name', $columns);
        $this->assertContains('project_customers.name', $columns);
        $this->assertContains('projects.start_date', $columns);
        $this->assertContains('projects.end_date', $columns);
    }

    public function test_active_method_exists(): void
    {
        $this->assertTrue(method_exists($this->query, 'active'));
    }

    public function test_archived_method_exists(): void
    {
        $this->assertTrue(method_exists($this->query, 'archived'));
    }

    public function test_trashed_method_exists(): void
    {
        $this->assertTrue(method_exists($this->query, 'trashed'));
    }

    public function test_find_trashed_by_name_method_exists(): void
    {
        $this->assertTrue(method_exists($this->query, 'findTrashedByName'));
    }

    public function test_state_counts_method_exists(): void
    {
        $this->assertTrue(method_exists($this->query, 'stateCounts'));
    }

    public function test_state_counts_returns_array_with_expected_keys(): void
    {
        $result = $this->query->stateCounts(10, 5, 3);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('active', $result);
        $this->assertArrayHasKey('inactive', $result);
        $this->assertArrayHasKey('trashed', $result);
        $this->assertSame(10, $result['active']);
        $this->assertSame(5, $result['inactive']);
        $this->assertSame(3, $result['trashed']);
    }

    public function test_with_active_counts_method_exists(): void
    {
        $reflection = new \ReflectionClass($this->query);
        $this->assertTrue($reflection->hasMethod('withActiveCounts'));
        $this->assertTrue($reflection->getMethod('withActiveCounts')->isPrivate());
    }

    public function test_with_customer_method_exists(): void
    {
        $reflection = new \ReflectionClass($this->query);
        $this->assertTrue($reflection->hasMethod('withCustomer'));
        $this->assertTrue($reflection->getMethod('withCustomer')->isPrivate());
    }
}
