<?php

namespace Database\Factories;

use App\Models\DocumentReviewEvent;
use App\Models\PatientDocument;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentReviewEvent>
 */
class DocumentReviewEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'patient_document_id' => PatientDocument::factory(),
            'user_id' => User::factory(),
            'target_type' => 'document_extraction',
            'target_id' => 1,
            'field_path' => 'payload',
            'previous_value' => [],
            'new_value' => [],
            'reason' => 'Clarified after reading source',
            'reviewed_at' => now(),
        ];
    }
}
