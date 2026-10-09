<?php

namespace Tests\Unit\Queries;

use Tests\TestCase;
use Basics13\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Test the abstract ListQueryBase through a concrete test subclass.
 */
final class ListQueryBaseTest extends TestCase
{
    private TestListQuery $query;

    protected function setUp(): void
    {
        parent::setUp();
        $this->query = new TestListQuery;
    }

    public function test_per_page_constant_is_25(): void
    {
        $this->assertSame(25, ListQueryBase::PER_PAGE);
    }

    public function test_like_escape_constant_is_exclamation_mark(): void
    {
        $reflection = new \ReflectionClass(ListQueryBase::class);
        $constant = $reflection->getConstant('LIKE_ESCAPE');
        $this->assertSame('!', $constant);
    }

    public function test_search_pattern_escapes_percent(): void
    {
        $result = $this->callSearchPattern('100%');
        $this->assertSame('%100!%%', $result);
    }

    public function test_search_pattern_escapes_underscore(): void
    {
        $result = $this->callSearchPattern('a_b');
        $this->assertSame('%a!_b%', $result);
    }

    public function test_search_pattern_escapes_escape_character(): void
    {
        $result = $this->callSearchPattern('!test');
        $this->assertSame('%!!test%', $result);
    }

    public function test_search_pattern_wraps_with_wildcards(): void
    {
        $result = $this->callSearchPattern('search');
        $this->assertSame('%search%', $result);
    }

    public function test_search_pattern_handles_multiple_special_chars(): void
    {
        $result = $this->callSearchPattern('a%_b!c');
        $this->assertSame('%a!%!_b!!c%', $result);
    }

    public function test_search_pattern_empty_string_returns_wildcards_only(): void
    {
        $result = $this->callSearchPattern('');
        $this->assertSame('%%', $result);
    }

    public function test_where_matches_returns_same_query_when_search_is_empty(): void
    {
        $builder = $this->createMock(Builder::class);
        $builder->expects($this->never())->method('where');

        $result = $this->callWhereMatches($builder, '', ['name']);

        $this->assertSame($builder, $result);
    }

    public function test_where_matches_adds_where_clause_when_search_is_provided(): void
    {
        $builder = $this->createMock(Builder::class);
        $builder->expects($this->once())
            ->method('where')
            ->willReturn($builder);

        $result = $this->callWhereMatches($builder, 'test', ['name']);

        $this->assertSame($builder, $result);
    }

    public function test_where_matches_adds_or_where_for_multiple_columns(): void
    {
        $builder = $this->createMock(Builder::class);
        $builder->expects($this->once())
            ->method('where')
            ->willReturn($builder);

        $result = $this->callWhereMatches($builder, 'test', ['name', 'email', 'notes']);

        $this->assertSame($builder, $result);
    }

    public function test_first_trashed_by_name_orders_by_deleted_at(): void
    {
        $builder = $this->createMock(Builder::class);
        $builder->expects($this->once())
            ->method('where')
            ->with('name', 'test')
            ->willReturnSelf();
        $builder->expects($this->once())
            ->method('latest')
            ->with('deleted_at')
            ->willReturnSelf();
        $builder->expects($this->once())
            ->method('first')
            ->willReturn(null);

        $model = $this->callFirstTrashedByName($builder, 'test');

        $this->assertNull($model);
    }

    private function callSearchPattern(string $search): string
    {
        $reflection = new \ReflectionMethod($this->query, 'searchPattern');
        $reflection->setAccessible(true);

        return $reflection->invoke($this->query, $search);
    }

    /**
     * @param  Builder<Model>  $builder
     * @param  array<int, string>  $searchColumns
     * @return Builder<Model>
     */
    private function callWhereMatches(Builder $builder, string $search, array $searchColumns): Builder
    {
        $reflection = new \ReflectionMethod($this->query, 'whereMatches');
        $reflection->setAccessible(true);

        return $reflection->invoke($this->query, $builder, $search, $searchColumns);
    }

    /**
     * @param  Builder<Model>  $builder
     */
    private function callFirstTrashedByName(Builder $builder, string $name): ?Model
    {
        $reflection = new \ReflectionMethod($this->query, 'firstTrashedByName');
        $reflection->setAccessible(true);

        return $reflection->invoke($this->query, $builder, $name);
    }
}

/**
 * Concrete implementation of ListQueryBase for testing.
 */
class TestListQuery extends ListQueryBase
{
    // No additional methods needed for testing base functionality
}
