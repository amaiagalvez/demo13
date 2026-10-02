<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Epic;
use App\Models\User;
use ReflectionClass;
use App\Models\Project;
use App\Models\Customer;
use App\Models\EpicComment;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Attributes\Fillable;

class ModelSchemaParityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Columns that are never mass assigned: primary key, timestamps, audit columns,
     * Fortify-managed fields, DB-generated uniqueness helpers and identity columns
     * set by route binding or authentication.
     *
     * @var array<int, string>
     */
    private const SYSTEM_COLUMNS = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
        'active',
        'remember_token',
        'email_verified_at',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'active_name',
        'epic_id',
        'user_id',
    ];

    private const MODELS = [
        Customer::class,
        Project::class,
        Epic::class,
        EpicComment::class,
        User::class,
    ];

    public function test_fillable_attributes_exist_in_the_schema(): void
    {
        foreach (self::MODELS as $model) {
            $columns = Schema::getColumnListing((new $model)->getTable());

            foreach ($this->fillableAttributes($model) as $attribute) {
                $this->assertContains(
                    $attribute,
                    $columns,
                    "{$model} is fillable on [{$attribute}] but the column does not exist",
                );
            }
        }
    }

    public function test_every_non_system_column_is_covered(): void
    {
        foreach (self::MODELS as $model) {
            $instance = new $model;
            $columns = Schema::getColumnListing($instance->getTable());
            $fillable = $this->fillableAttributes($model);

            foreach ($columns as $column) {
                if (in_array($column, self::SYSTEM_COLUMNS, true)) {
                    continue;
                }

                $this->assertContains(
                    $column,
                    $fillable,
                    "{$model} column [{$column}] is not in #[Fillable]: add it there (and a validation rule) or to SYSTEM_COLUMNS if it is server-set",
                );
            }
        }
    }

    public function test_casts_and_soft_deletes_match_the_schema(): void
    {
        foreach (self::MODELS as $model) {
            $instance = new $model;
            $columns = Schema::getColumnListing($instance->getTable());

            foreach ($instance->getCasts() as $key => $value) {
                $this->assertContains(
                    $key,
                    $columns,
                    "{$model} casts [{$key}] but the column does not exist",
                );
            }

            if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                $this->assertContains(
                    'deleted_at',
                    $columns,
                    "{$model} uses SoftDeletes but has no deleted_at column",
                );
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
}
