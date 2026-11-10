<?php

namespace Database\Factories;

use App\Enums\WorkflowKey;
use App\Models\Team;
use App\Models\WorkflowDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkflowDefinition>
 */
class WorkflowDefinitionFactory extends Factory
{
    protected $model = WorkflowDefinition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = WorkflowKey::NewPatientEnquiry;

        return [
            'team_id' => Team::factory(),
            'uuid' => (string) Str::uuid(),
            'key' => $key,
            'name' => $key->label(),
            'trigger' => $key->trigger(),
            'is_enabled' => true,
            'steps' => [
                [
                    'key' => 'notify_coordinator',
                    'action' => 'notify_staff',
                    'audience' => 'coordinator',
                    'summary' => 'Test workflow notification',
                ],
            ],
            'description' => null,
        ];
    }
}
