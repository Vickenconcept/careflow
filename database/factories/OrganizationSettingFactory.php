<?php

namespace Database\Factories;

use App\Models\OrganizationSetting;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationSetting>
 */
class OrganizationSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'legal_name' => fake()->company(),
            'contact_email' => fake()->companyEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'address_line_1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => fake()->countryCode(),
            'timezone' => 'Asia/Nicosia',
            'default_currency' => 'EUR',
            'default_language' => 'en',
            'supported_languages' => ['en', 'tr', 'ar', 'ru', 'az'],
            'ai_processing_enabled' => false,
            'ai_provider' => 'openai',
            'enabled_modules' => ['intake', 'crm', 'documents'],
        ];
    }
}
