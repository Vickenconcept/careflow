<?php

namespace Database\Factories;

use App\Domains\Documents\Data\StructuredExtractionResult;
use App\Models\DocumentExtraction;
use App\Models\DocumentProcessingRun;
use App\Models\PatientDocument;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentExtraction>
 */
class DocumentExtractionFactory extends Factory
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
            'schema_version' => '2026-11-01',
            'payload' => StructuredExtractionResult::emptySchema(),
            'corrected_payload' => null,
            'provider' => 'mock',
            'is_synthetic' => true,
            'review_status' => 'unreviewed',
            'is_current' => true,
        ];
    }
}
