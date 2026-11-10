<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\CaseTaskCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'team_id',
    'case_task_id',
    'author_user_id',
    'body',
])]
class CaseTaskComment extends Model
{
    /** @use HasFactory<CaseTaskCommentFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<CaseTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(CaseTask::class, 'case_task_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
