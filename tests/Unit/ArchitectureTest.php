<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Epic;
use ReflectionClass;
use ReflectionMethod;
use App\Models\Project;
use App\Models\Customer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;

class ArchitectureTest extends TestCase
{
    /**
     * Resource name (singular) => model class.
     *
     * @var array<string, class-string>
     */
    private const RESOURCES = [
        'Customer' => Customer::class,
        'Project' => Project::class,
        'Epic' => Epic::class,
    ];

    private const FORM_REQUEST_ENDPOINTS = ['index', 'store', 'update', 'restore', 'comment'];

    public function test_every_resource_ships_the_complete_set(): void
    {
        foreach (self::RESOURCES as $resource => $modelClass) {
            $plural = strtolower($resource).'s';

            $classes = [
                "App\\Http\\Controllers\\{$resource}Controller",
                "App\\Http\\Controllers\\{$resource}TrashController",
                "App\\Http\\Requests\\{$resource}Request",
                "App\\Http\\Requests\\{$resource}ListRequest",
                "App\\Http\\Requests\\{$resource}RestoreRequest",
                "App\\Policies\\{$resource}Policy",
                "App\\Queries\\{$resource}s\\{$resource}ListQuery",
                "App\\Transformers\\{$resource}ListTransformer",
                "Database\\Factories\\{$resource}Factory",
            ];

            foreach ($classes as $class) {
                $this->assertTrue(class_exists($class), "Missing class {$class}");
            }

            $model = new ReflectionClass($modelClass);
            $this->assertNotEmpty(
                $model->getAttributes(UsePolicy::class),
                "{$resource} must declare #[UsePolicy]",
            );

            foreach (['list', 'form'] as $view) {
                $this->assertFileExists(resource_path("views/{$plural}/{$view}.blade.php"));
            }
        }
    }

    public function test_resource_view_abilities_match_show_routes(): void
    {
        foreach (self::RESOURCES as $resource => $modelClass) {
            $policy = new ReflectionClass("App\\Policies\\{$resource}Policy");
            $hasShowRoute = Route::has(strtolower($resource).'s.show');

            $this->assertSame(
                $hasShowRoute,
                $policy->hasMethod('view'),
                "{$resource}Policy::view must exist exactly when its show route exists",
            );
        }
    }

    public function test_soft_deletable_models_declare_a_policy(): void
    {
        foreach (glob(app_path('Models/*.php')) ?: [] as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');

            if (
                ! class_exists($class)
                || ! in_array(SoftDeletes::class, class_uses_recursive($class), true)
            ) {
                continue;
            }

            $model = new ReflectionClass($class);

            $this->assertNotEmpty(
                $model->getAttributes(UsePolicy::class),
                "{$class} must declare #[UsePolicy] because it uses SoftDeletes",
            );
        }
    }

    public function test_controllers_stay_thin_and_free_of_raw_input(): void
    {
        foreach ($this->controllerFiles() as $file) {
            $source = (string) file_get_contents($file);

            $this->assertDoesNotMatchRegularExpression(
                '/DB::(?!transaction\s*\()/',
                $source,
                "{$file} may use DB::transaction() but no other DB facade calls",
            );
            $this->assertStringNotContainsString('Schema::', $source, "{$file} must not touch Schema::");
            $this->assertDoesNotMatchRegularExpression('/\b(?:dd|dump)\s*\(/', $source, "{$file} contains dd/dump");
            $this->assertStringNotContainsString(
                '->paginate(',
                $source,
                "{$file} must paginate in app/Queries, not in the controller",
            );
            $this->assertDoesNotMatchRegularExpression(
                '/\$request->(?:input|all|only|except)\s*\(/',
                $source,
                "{$file} reads raw input; use a FormRequest and validated data",
            );
            $this->assertDoesNotMatchRegularExpression(
                '/(?<!\w)Request\s+\$request\b/',
                $source,
                "{$file} type-hints the base Request; every input endpoint needs a FormRequest",
            );
        }
    }

    public function test_input_endpoints_declare_a_form_request(): void
    {
        foreach ($this->controllerFiles() as $file) {
            $class = 'App\\Http\\Controllers\\'.basename($file, '.php');

            if (! class_exists($class)) {
                $this->fail("Missing class {$class}");
            }

            $reflection = new ReflectionClass($class);

            /** @var ReflectionMethod $method */
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                    continue;
                }

                if (! in_array($method->getName(), self::FORM_REQUEST_ENDPOINTS, true)) {
                    continue;
                }

                $declaresFormRequest = array_filter(
                    $method->getParameters(),
                    fn ($parameter): bool => $parameter->hasType()
                        && is_a((string) $parameter->getType(), FormRequest::class, true),
                );

                $this->assertNotEmpty(
                    $declaresFormRequest,
                    "{$class}::{$method->getName()} must declare a FormRequest (user input must be validated)",
                );
            }
        }
    }

    public function test_views_follow_the_canonical_patterns(): void
    {
        foreach (['customers', 'projects', 'epics'] as $plural) {
            $list = (string) file_get_contents(resource_path("views/{$plural}/list.blade.php"));
            $form = (string) file_get_contents(resource_path("views/{$plural}/form.blade.php"));

            $this->assertStringContainsString(
                '<x-list.table',
                $list,
                "{$plural}/list.blade.php must use <x-list.table> instead of its own table markup",
            );
            $this->assertStringContainsString(
                '<x-forms.tracked-resource',
                $form,
                "{$plural}/form.blade.php must use <x-forms.tracked-resource>",
            );
        }
    }

    public function test_raw_blade_echo_is_limited_to_the_two_factor_qr_code(): void
    {
        $rawEchoesByFile = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            preg_match_all('/\{!!.*?!!\}/s', (string) file_get_contents($file->getPathname()), $matches);

            if ($matches[0] !== []) {
                $rawEchoesByFile[$file->getPathname()] = count($matches[0]);
            }
        }

        $this->assertSame([
            resource_path('views/pages/settings/⚡two-factor-setup-modal.blade.php') => 1,
        ], $rawEchoesByFile);
    }

    public function test_form_requests_expose_rules_and_authorize(): void
    {
        foreach (glob(app_path('Http/Requests/*.php')) ?: [] as $file) {
            $class = 'App\\Http\\Requests\\'.basename($file, '.php');

            if (! class_exists($class)) {
                $this->fail("Missing class {$class}");
            }

            $reflection = new ReflectionClass($class);

            $this->assertTrue(
                $reflection->isSubclassOf(FormRequest::class),
                "{$reflection->getName()} must extend FormRequest",
            );

            if ($reflection->isAbstract()) {
                continue;
            }

            foreach (['rules', 'authorize'] as $method) {
                $this->assertTrue(
                    $reflection->hasMethod($method) && $reflection->getMethod($method)->isPublic(),
                    "{$reflection->getName()} must define public {$method}()",
                );
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function controllerFiles(): array
    {
        $files = glob(app_path('Http/Controllers/*.php')) ?: [];

        return array_values(array_filter(
            $files,
            fn (string $file): bool => basename($file) !== 'Controller.php',
        ));
    }
}
