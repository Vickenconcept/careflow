<?php

namespace Database\Factories;

use App\Models\DocumentProcessingRun;
use App\Models\DocumentTranslation;
use App\Models\PatientDocument;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentTranslation>
 */
class DocumentTranslationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'patient_document_id' => PatientDocument::factory(),
            'document_processing_run_id' => DocumentProcessingRun::factory(),
            'version' => 1,
            'source_language' => 'en',
            'target_language' => 'en',
            'translated_text' => 'Mock translation',
            'provider' => 'mock',
            'status' => 'completed',
            'disclaimer' => 'AI-assisted translation. Not a certified medical translation.',
            'is_synthetic' => true,
            'is_current' => true,
            'reviewed_by' => null,
            'review_outcome' => null,
            'translated_at' => now(),
        ];
    }
}
