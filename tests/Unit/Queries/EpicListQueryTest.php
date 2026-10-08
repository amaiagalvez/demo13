<?php

namespace Tests\Unit\Queries;

use Tests\TestCase;
use App\Queries\Epics\EpicListQuery;

class EpicListQueryTest extends TestCase
{
    private EpicListQuery $query;

    protected function setUp(): void
    {
        parent::setUp();
        $this->query = new EpicListQuery;
    }

    public function test_recent_comments_limit_constant_is_20(): void
    {
        $this->assertSame(20, EpicListQuery::RECENT_COMMENTS_LIMIT);
    }

    public function test_search_columns_constant_exists(): void
    {
        $reflection = new \ReflectionClass($this->query);
        $this->assertTrue($reflection->hasConstant('SEARCH_COLUMNS'));
        $columns = $reflection->getConstant('SEARCH_COLUMNS');
        $this->assertIsArray($columns);
        $this->assertContains('epics.name', $columns);
        $this->assertContains('epic_projects.name', $columns);
        $this->assertContains('epic_customers.name', $columns);
        $this->assertContains('epics.start_date', $columns);
        $this->assertContains('epics.end_date', $columns);
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

    public function test_find_trashed_by_name_signature_accepts_project_id(): void
    {
        $reflection = new \ReflectionMethod($this->query, 'findTrashedByName');
        $params = $reflection->getParameters();
        $this->assertCount(2, $params);
        $this->assertSame('name', $params[0]->getName());
        $this->assertSame('projectId', $params[1]->getName());
    }

    public function test_with_project_and_customer_method_exists(): void
    {
        $reflection = new \ReflectionClass($this->query);
        $this->assertTrue($reflection->hasMethod('withProjectAndCustomer'));
        $this->assertTrue($reflection->getMethod('withProjectAndCustomer')->isPrivate());
    }
}
