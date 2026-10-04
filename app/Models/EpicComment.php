<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use App\Concerns\TracksAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Database\Factories\EpicCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Comments are created through Epic's comment ability; they have no independent mutation policy.
 *
 * @property int $id
 * @property int $epic_id
 * @property int|null $user_id
 * @property string $body
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['body', 'notes'])]
class EpicComment extends Model
{
    /** @use HasFactory<EpicCommentFactory> */
    use HasFactory, TracksAuditColumns;

    /**
     * @return BelongsTo<Epic, $this>
     */
    public function epic(): BelongsTo
    {
        return $this->belongsTo(Epic::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
