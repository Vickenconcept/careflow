<?php

namespace Database\Factories;

use App\Models\CaseTask;
use App\Models\CaseTaskComment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaseTaskComment>
 */
class CaseTaskCommentFactory extends Factory
{
    protected $model = CaseTaskComment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'case_task_id' => CaseTask::factory(),
            'author_user_id' => User::factory(),
            'body' => 'Task note',
        ];
    }
}
