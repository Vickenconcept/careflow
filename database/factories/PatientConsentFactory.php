<?php

namespace Database\Factories;

use App\Enums\ConsentType;
use App\Models\Patient;
use App\Models\PatientConsent;
use App\Models\PatientEnquiry;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PatientConsent>
 */
class PatientConsentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'patient_id' => Patient::factory(),
            'patient_enquiry_id' => PatientEnquiry::factory(),
            'submission_uuid' => (string) Str::uuid(),
            'consent_type' => ConsentType::Enquiry,
            'privacy_notice_version' => '2026-11-01',
            'consent_text_version' => '2026-11-01',
            'accepted' => true,
            'consented_at' => now(),
            'source_ip' => '127.0.0.1',
        ];
    }
}
