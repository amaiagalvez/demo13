<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

class PackageViewsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_package_views_are_loaded(): void
    {
        $providers = array_keys($this->app->getLoadedProviders());
        $this->assertContains('Basics13\ServiceProvider', $providers, 'Basics13 ServiceProvider not loaded');

        $this->assertTrue(View::exists('basics13::components.list.create-action'));
    }

    public function test_package_view_can_be_rendered(): void
    {
        $html = view('basics13::components.list.create-action', [
            'prefix' => 'test',
            'label' => 'Test Label',
            'click' => 'testClick()',
        ])->render();

        $this->assertStringContainsString('Test Label', $html);
        $this->assertStringContainsString('testClick()', $html);
    }

    public function test_package_component_in_blade_template(): void
    {
        $blade = <<<'BLADE'
            <x-basics13::list.create-action prefix="customer" :label="__('New customer')" click="createCustomer()" />
        BLADE;

        $html = Blade::render($blade);

        $this->assertStringContainsString(__('New customer'), $html);
        $this->assertStringContainsString('createCustomer()', $html);
    }

    public function test_package_components_can_render_nested_components(): void
    {
        $html = Blade::render(
            '<x-basics13::list.page-header :list="$list" prefix="customer" />',
            ['list' => [
                'breadcrumbs' => [['label' => 'Customers', 'url' => null]],
                'tabs' => [],
            ]],
        );

        $this->assertStringContainsString('customer-heading', $html);
        $this->assertStringContainsString('Customers', $html);
    }

    public function test_package_validation_translations_are_available_to_the_validator(): void
    {
        $this->app->setLocale('eu');

        $attributes = __('validation.attributes');

        $this->assertIsArray($attributes);
        $this->assertSame(__('basics13::validation.attributes.name'), $attributes['name']);
    }

    public function test_package_table_keeps_the_pagination_summary_for_an_empty_list(): void
    {
        $html = Blade::render(
            '<x-basics13::list.table prefix="customer" :paginator="$paginator" />',
            ['paginator' => new LengthAwarePaginator([], 0, 15)],
        );

        $this->assertStringContainsString(__('Showing'), $html);
        $this->assertStringContainsString(__('results'), $html);
    }

    #[DataProvider('packagePageProvider')]
    public function test_application_pages_render_package_components(string $route): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route($route))
            ->assertOk();
    }

    /** @return array<string, array{string}> */
    public static function packagePageProvider(): array
    {
        return [
            'customers' => ['customers.index'],
            'customer trash' => ['customers.trash.index'],
            'customer archive' => ['customers.archived.index'],
            'projects' => ['projects.index'],
            'project trash' => ['projects.trash.index'],
            'project archive' => ['projects.archived.index'],
            'epics' => ['epics.index'],
            'epic trash' => ['epics.trash.index'],
            'epic archive' => ['epics.archived.index'],
            'planning' => ['planning'],
            'timeline' => ['timeline'],
        ];
    }
}
