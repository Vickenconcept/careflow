<?php

namespace Database\Factories;

use App\Models\DocumentProcessingRun;
use App\Models\DocumentSummary;
use App\Models\PatientDocument;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentSummary>
 */
class DocumentSummaryFactory extends Factory
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
            'sections' => [
                'document_overview' => 'Mock overview',
            ],
            'corrected_sections' => null,
            'provider' => 'mock',
            'disclaimer' => 'AI-generated draft. Requires review by an authorized healthcare professional.',
            'is_synthetic' => true,
            'review_status' => 'unreviewed',
            'is_current' => true,
        ];
    }
}
