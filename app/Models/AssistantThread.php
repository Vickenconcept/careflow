<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\AssistantThreadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Points a staff member at their current assistant conversation inside one
 * hospital. The conversation itself is stored by Laravel AI.
 */
#[Fillable(['team_id', 'user_id', 'conversation_id'])]
class AssistantThread extends Model
{
    /** @use HasFactory<AssistantThreadFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
