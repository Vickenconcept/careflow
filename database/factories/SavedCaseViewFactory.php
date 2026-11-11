<?php

namespace Database\Factories;

use App\Models\SavedCaseView;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedCaseView>
 */
class SavedCaseViewFactory extends Factory
{
    protected $model = SavedCaseView::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'filters' => ['priority' => 'urgent'],
        ];
    }
}
