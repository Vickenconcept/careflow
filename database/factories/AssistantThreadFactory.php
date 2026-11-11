<?php

namespace Database\Factories;

use App\Models\AssistantThread;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AssistantThread>
 */
class AssistantThreadFactory extends Factory
{
    protected $model = AssistantThread::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'conversation_id' => (string) Str::uuid(),
        ];
    }
}
