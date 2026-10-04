<?php

namespace Tests\Unit\Transformers;

use Tests\TestCase;
use App\Transformers\EpicListTransformer;
use App\Transformers\ProjectListTransformer;
use App\Transformers\CustomerListTransformer;
use Illuminate\Pagination\LengthAwarePaginator;

class ListTransformerContractTest extends TestCase
{
    private const ACTIVE_KEYS = [
        'resource',
        'breadcrumbs',
        'extraDateHeading',
        'emptyMessage',
        'search',
        'tabs',
        'create',
        'rows',
    ];

    public function test_customer_active_payload_has_the_expected_keys(): void
    {
        $list = app(CustomerListTransformer::class)->active(new LengthAwarePaginator([], 0, 15), '');

        $this->assertSame(self::ACTIVE_KEYS, array_keys($list));
        $this->assertSame(__('Created at'), $list['extraDateHeading']);
    }

    public function test_project_active_payload_has_the_expected_keys(): void
    {
        $list = app(ProjectListTransformer::class)->active(new LengthAwarePaginator([], 0, 15), '');

        $this->assertSame(self::ACTIVE_KEYS, array_keys($list));
        $this->assertSame(__('Created at'), $list['extraDateHeading']);
    }

    public function test_epic_active_payload_has_the_expected_keys(): void
    {
        $list = app(EpicListTransformer::class)->active(new LengthAwarePaginator([], 0, 15), '');

        $this->assertSame(self::ACTIVE_KEYS, array_keys($list));
        $this->assertSame(__('Created at'), $list['extraDateHeading']);
    }

    public function test_state_tabs_count_records_only_when_counts_are_given(): void
    {
        $transformer = app(CustomerListTransformer::class);
        $paginator = new LengthAwarePaginator([], 0, 15);

        /** @var array<string, mixed> $withoutCounts */
        $withoutCounts = $transformer->active($paginator, '');
        /** @var array<string, mixed> $withCounts */
        $withCounts = $transformer->active($paginator, '', [
            'active' => 2,
            'inactive' => 1,
            'trashed' => 3,
        ]);

        /** @var list<array{count: int|null}> $tabsWithoutCounts */
        $tabsWithoutCounts = $withoutCounts['tabs'];
        /** @var list<array{count: int|null}> $tabsWithCounts */
        $tabsWithCounts = $withCounts['tabs'];

        $this->assertSame([null, null, null], array_column($tabsWithoutCounts, 'count'));
        $this->assertSame([2, 1, 3], array_column($tabsWithCounts, 'count'));
    }
}
