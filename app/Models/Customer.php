<?php

namespace App\Models;

use App\Policies\CustomerPolicy;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property bool $active
 * @property-read bool $projects_exists
 */
#[Fillable(['name'])]
#[UsePolicy(CustomerPolicy::class)]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array{active: bool}
     */
    protected $attributes = [
        'active' => true,
    ];

    /**
     * @return array{active: 'boolean'}
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
