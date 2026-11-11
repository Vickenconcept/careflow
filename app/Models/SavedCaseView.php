<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\SavedCaseViewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A staff member's personal, named set of case list filters.
 */
#[Fillable(['team_id', 'user_id', 'name', 'filters'])]
class SavedCaseView extends Model
{
    /** @use HasFactory<SavedCaseViewFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
        ];
    }
}
