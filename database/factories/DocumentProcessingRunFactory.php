<?php

namespace Database\Factories;

use App\Enums\DocumentProcessingStatus;
use App\Models\DocumentProcessingRun;
use App\Models\PatientDocument;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DocumentProcessingRun>
 */
class DocumentProcessingRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'patient_document_id' => PatientDocument::factory(),
            'uuid' => (string) Str::uuid(),
            'provider' => 'mock',
            'status' => DocumentProcessingStatus::NeedsReview,
            'attempt' => 1,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'usage' => ['provider' => 'mock', 'synthetic' => true],
            'error_message' => null,
        ];
    }
}
