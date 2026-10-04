<?php

namespace App\Models;

use App\Policies\EpicPolicy;
use Database\Factories\EpicFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property string $name
 * @property string|null $notes
 * @property bool $active
 * @property int $project_id
 * @property-read Project $project
 * @property-read int $comments_count
 */
#[Fillable(['name', 'notes', 'start_date', 'end_date', 'project_id'])]
#[UsePolicy(EpicPolicy::class)]
class Epic extends Model
{
    /** @use HasFactory<EpicFactory> */
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
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * @return HasMany<EpicComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(EpicComment::class);
    }
}
