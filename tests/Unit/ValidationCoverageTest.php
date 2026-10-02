<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Epic;
use ReflectionClass;
use App\Models\Project;
use App\Models\Customer;
use App\Models\EpicComment;
use App\Http\Requests\EpicRequest;
use App\Http\Requests\ProjectRequest;
use App\Http\Requests\CustomerRequest;
use App\Http\Requests\EpicCommentRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Database\Eloquent\Attributes\Fillable;

class ValidationCoverageTest extends TestCase
{
    /**
     * Models whose user input is validated by a project FormRequest.
     * User is excluded: registration and profile updates are handled by Fortify.
     *
     * @var array<class-string, class-string>
     */
    private const PAIRS = [
        Customer::class => CustomerRequest::class,
        Project::class => ProjectRequest::class,
        Epic::class => EpicRequest::class,
        EpicComment::class => EpicCommentRequest::class,
    ];

    public function test_every_fillable_attribute_has_a_validation_rule(): void
    {
        foreach (self::PAIRS as $model => $request) {
            $fillable = $this->fillableAttributes($model);
            $this->assertNotEmpty($fillable, "{$model} must declare #[Fillable]");

            $rules = $this->rulesOf($request);

            foreach ($fillable as $attribute) {
                $this->assertArrayHasKey(
                    $attribute,
                    $rules,
                    "{$model}::\${$attribute} is mass assignable but has no rule in {$request}",
                );
            }
        }
    }

    public function test_input_read_by_controllers_is_validated(): void
    {
        foreach (glob(app_path('Http/Controllers/*.php')) ?: [] as $file) {
            if (basename($file) === 'Controller.php') {
                continue;
            }

            $source = (string) file_get_contents($file);
            $blocks = preg_split('/(?=public function )/', $source) ?: [];

            foreach ($blocks as $block) {
                if (! preg_match('/public function (\w+)\s*\(/', $block, $method)) {
                    continue;
                }

                if (! preg_match_all('/\$request->(?:string|boolean)\(\s*[\'"]([^\'"]+)[\'"]/', $block, $keys)) {
                    continue;
                }

                if (preg_match('/(\w+Request)\s+\$request\b/', $block, $request) !== 1) {
                    $this->fail(
                        basename($file).'::'.$method[1].' reads input without declaring a FormRequest',
                    );
                }

                $rules = $this->rulesOf('App\\Http\\Requests\\'.$request[1]);

                foreach (array_unique($keys[1]) as $key) {
                    $this->assertArrayHasKey(
                        $key,
                        $rules,
                        basename($file).'::'.$method[1]." reads \${$key} but {$request[1]} has no rule for it",
                    );
                }
            }
        }
    }

    /**
     * @param  class-string  $model
     * @return array<int, string>
     */
    private function fillableAttributes(string $model): array
    {
        $attributes = (new ReflectionClass($model))
            ->getAttributes(Fillable::class);

        return $attributes !== [] ? $attributes[0]->getArguments()[0] : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesOf(string $request): array
    {
        /** @var FormRequest $instance */
        $instance = new $request;
        $instance->setRouteResolver(fn () => null);

        if (! method_exists($instance, 'rules')) {
            $this->fail("{$request} must define rules()");
        }

        $rules = $instance->rules();
        $this->assertIsArray($rules);
        $this->assertNotSame([], $rules, "{$request}::rules() must not be empty");

        return $rules;
    }
}
