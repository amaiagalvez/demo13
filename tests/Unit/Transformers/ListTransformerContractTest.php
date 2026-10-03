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
        'state',
        'extraDateHeading',
        'inactiveUrl',
        'emptyMessage',
        'search',
        'navigation',
        'create',
        'rows',
    ];

    /**
     * Customers already moved to the page header (breadcrumbs + state tabs), so they no longer
     * carry the toolbar keys.
     */
    private const CUSTOMER_ACTIVE_KEYS = [
        'resource',
        'state',
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

        $this->assertSame(self::CUSTOMER_ACTIVE_KEYS, array_keys($list));
        $this->assertSame(__('Created at'), $list['extraDateHeading']);
    }

    public function test_project_active_payload_has_the_expected_keys(): void
    {
        $list = app(ProjectListTransformer::class)->active(new LengthAwarePaginator([], 0, 15), '');

        $this->assertSame(self::ACTIVE_KEYS, array_keys($list));
        $this->assertNull($list['extraDateHeading']);
    }

    public function test_epic_active_payload_has_the_expected_keys(): void
    {
        $list = app(EpicListTransformer::class)->active(new LengthAwarePaginator([], 0, 15), '');

        $this->assertSame(self::ACTIVE_KEYS, array_keys($list));
        $this->assertNull($list['extraDateHeading']);
    }
}
