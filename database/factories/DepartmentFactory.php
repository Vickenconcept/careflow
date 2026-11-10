<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Cardiology',
            'Orthopedics',
            'IVF & Fertility',
            'Oncology',
            'Neurology',
            'Plastic Surgery',
            'General Surgery',
            'Internal Medicine',
        ]);

        return [
            'team_id' => Team::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'code' => strtoupper(Str::substr(Str::slug($name, ''), 0, 6)),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }

    /**
     * Indicate that the department is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
