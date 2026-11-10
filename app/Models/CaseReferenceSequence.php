<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $team_id
 * @property int $year
 * @property int $last_number
 */
#[Fillable(['team_id', 'year', 'last_number'])]
class CaseReferenceSequence extends Model
{
    use BelongsToTeam;
}
