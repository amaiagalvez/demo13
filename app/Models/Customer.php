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
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property bool $active
 * @property-read bool $projects_exists
 * @property-read int $projects_count
 * @property-read int $epics_count
 * @property-read int $comments_count
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

    /**
     * Epics reached through the projects of this customer; trashed projects are excluded.
     *
     * @return HasManyThrough<Epic, Project, $this>
     */
    public function epics(): HasManyThrough
    {
        return $this->hasManyThrough(Epic::class, Project::class, 'customer_id', 'project_id');
    }
}
