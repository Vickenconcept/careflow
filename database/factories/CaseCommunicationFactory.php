<?php

namespace Database\Factories;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationDirection;
use App\Enums\CommunicationStatus;
use App\Models\CaseCommunication;
use App\Models\PatientCase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CaseCommunication>
 */
class CaseCommunicationFactory extends Factory
{
    protected $model = CaseCommunication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'patient_case_id' => PatientCase::factory(),
            'created_by_user_id' => User::factory(),
            'approved_by_user_id' => null,
            'uuid' => (string) Str::uuid(),
            'channel' => CommunicationChannel::Internal,
            'direction' => CommunicationDirection::Internal,
            'status' => CommunicationStatus::Logged,
            'language' => 'en',
            'subject' => null,
            'body' => 'Internal note',
            'sender' => null,
            'recipient' => null,
            'provider_reference' => null,
            'is_ai_draft' => false,
            'requires_approval' => false,
            'approved_at' => null,
            'sent_at' => null,
            'ai_metadata' => null,
            'is_demo' => false,
        ];
    }
}
