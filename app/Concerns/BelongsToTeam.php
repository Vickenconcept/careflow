<?php

namespace App\Concerns;

use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $team_id
 * @property-read Team $team
 *
 * @method static Builder<static> forTeam(Team|int $team)
 */
trait BelongsToTeam
{
    /**
     * Get the team (organization tenant) that owns this record.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Scope a query to a specific team tenant.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForTeam(Builder $query, Team|int $team): Builder
    {
        $teamId = $team instanceof Team ? $team->id : $team;

        return $query->where($this->qualifyColumn('team_id'), $teamId);
    }
}
