<?php

namespace Database\Factories;

use App\Enums\DocumentCategory;
use App\Enums\DocumentProcessingStatus;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientDocument;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PatientDocument>
 */
class PatientDocumentFactory extends Factory
{
    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'team_id' => Team::factory(),
            'patient_id' => Patient::factory(),
            'patient_case_id' => PatientCase::factory(),
            'uuid' => $uuid,
            'original_filename' => 'report.pdf',
            'disk' => 'local',
            'path' => 'teams/1/cases/1/documents/'.$uuid.'.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 1024,
            'checksum_sha256' => hash('sha256', 'demo'),
            'category' => DocumentCategory::MedicalReport,
            'processing_status' => DocumentProcessingStatus::Uploaded,
            'original_language' => null,
            'processing_error' => null,
            'uploaded_at' => now(),
        ];
    }
}
