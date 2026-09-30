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

#[Fillable(['name', 'start_date', 'end_date', 'project_id'])]
#[UsePolicy(EpicPolicy::class)]
class Epic extends Model
{
    /** @use HasFactory<EpicFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(EpicComment::class);
    }
}
