<?php

namespace Database\Factories;

use App\Enums\PatientGender;
use App\Models\Patient;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'uuid' => (string) Str::uuid(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(PatientGender::cases()),
            'country_of_residence' => fake()->countryCode(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'preferred_language' => fake()->randomElement(['en', 'tr', 'ar', 'ru', 'az']),
            'is_demo' => false,
        ];
    }

    public function demo(): static
    {
        return $this->state(fn () => ['is_demo' => true]);
    }
}
