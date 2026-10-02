<?php

namespace App\Models;

use App\Policies\ProjectPolicy;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property bool $active
 * @property-read bool $epics_exists
 */
#[Fillable(['name', 'start_date', 'end_date', 'customer_id'])]
#[UsePolicy(ProjectPolicy::class)]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array{active: bool}
     */
    protected $attributes = [
        'active' => true,
    ];

    /**
     * @return array{start_date: 'date', end_date: 'date', active: 'boolean'}
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return HasMany<Epic, $this>
     */
    public function epics(): HasMany
    {
        return $this->hasMany(Epic::class);
    }
}
