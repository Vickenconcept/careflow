<?php

namespace App\Data\Patients;

use App\Enums\DocumentCategory;
use App\Enums\PatientGender;
use App\Models\Team;
use Illuminate\Http\UploadedFile;

readonly class IntakeSubmissionData
{
    /**
     * @param  array<int, array{file: UploadedFile, category: DocumentCategory}>  $documents
     */
    public function __construct(
        public Team $team,
        public string $firstName,
        public string $lastName,
        public ?string $dateOfBirth,
        public ?PatientGender $gender,
        public ?string $countryOfResidence,
        public string $email,
        public ?string $phone,
        public string $preferredLanguage,
        public ?int $departmentId,
        public string $reasonForContact,
        public ?string $diagnosis,
        public ?string $symptomsOrHistory,
        public ?string $previousProcedures,
        public ?string $currentMedications,
        public ?string $knownAllergies,
        public ?string $additionalComments,
        public bool $enquiryConsentAccepted,
        public bool $healthDataConsentAccepted,
        public bool $aiProcessingConsentAccepted,
        public array $documents,
        public ?string $sourceIp,
        public ?string $userAgent,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<int, UploadedFile>  $uploadedFiles
     * @param  array<int, string|null>  $categories
     */
    public static function fromValidatedRequest(
        Team $team,
        array $validated,
        array $uploadedFiles,
        array $categories,
        ?string $sourceIp,
        ?string $userAgent,
    ): self {
        $documents = [];

        foreach ($uploadedFiles as $index => $file) {
            $categoryValue = $categories[$index] ?? DocumentCategory::Unknown->value;

            $documents[] = [
                'file' => $file,
                'category' => DocumentCategory::tryFrom((string) $categoryValue) ?? DocumentCategory::Unknown,
            ];
        }

        return new self(
            team: $team,
            firstName: (string) $validated['first_name'],
            lastName: (string) $validated['last_name'],
            dateOfBirth: $validated['date_of_birth'] ?? null,
            gender: isset($validated['gender'])
                ? PatientGender::from((string) $validated['gender'])
                : null,
            countryOfResidence: isset($validated['country_of_residence'])
                ? strtoupper((string) $validated['country_of_residence'])
                : null,
            email: strtolower((string) $validated['email']),
            phone: $validated['phone'] ?? null,
            preferredLanguage: (string) $validated['preferred_language'],
            departmentId: isset($validated['department_id']) ? (int) $validated['department_id'] : null,
            reasonForContact: (string) $validated['reason_for_contact'],
            diagnosis: $validated['diagnosis'] ?? null,
            symptomsOrHistory: $validated['symptoms_or_history'] ?? null,
            previousProcedures: $validated['previous_procedures'] ?? null,
            currentMedications: $validated['current_medications'] ?? null,
            knownAllergies: $validated['known_allergies'] ?? null,
            additionalComments: $validated['additional_comments'] ?? null,
            enquiryConsentAccepted: (bool) $validated['enquiry_consent'],
            healthDataConsentAccepted: (bool) $validated['health_data_consent'],
            aiProcessingConsentAccepted: (bool) ($validated['ai_processing_consent'] ?? false),
            documents: $documents,
            sourceIp: $sourceIp,
            userAgent: $userAgent,
        );
    }
}
