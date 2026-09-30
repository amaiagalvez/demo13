<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Database\Factories\EpicCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['body'])]
class EpicComment extends Model
{
    /** @use HasFactory<EpicCommentFactory> */
    use HasFactory;

    public function epic(): BelongsTo
    {
        return $this->belongsTo(Epic::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
