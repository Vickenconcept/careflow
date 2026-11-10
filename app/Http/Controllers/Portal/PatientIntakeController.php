<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Patients\SubmitPatientIntake;
use App\Data\Patients\IntakeSubmissionData;
use App\Enums\DocumentCategory;
use App\Enums\PatientGender;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\SubmitPatientIntakeRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class PatientIntakeController extends Controller
{
    public function create(Request $request, Team $organization): Response
    {
        $settings = $organization->ensureOrganizationSetting();

        $supportedLanguages = $settings->supported_languages
            ?: config('careflow.portal.supported_languages');

        return Inertia::render('portal/intake', [
            'organization' => [
                'name' => $settings->legal_name ?: $organization->name,
                'slug' => $organization->slug,
                'brand_primary_color' => $settings->brand_primary_color,
                'default_language' => $settings->default_language,
                'supported_languages' => $supportedLanguages,
            ],
            'departments' => $organization->departments()
                ->where('is_active', true)
                ->get(['id', 'name', 'slug'])
                ->map(fn ($department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'slug' => $department->slug,
                ]),
            'genderOptions' => collect(PatientGender::cases())
                ->map(fn (PatientGender $gender) => [
                    'value' => $gender->value,
                    'label' => $gender->label(),
                ])
                ->values(),
            'documentCategories' => collect(DocumentCategory::cases())
                ->reject(fn (DocumentCategory $category) => $category === DocumentCategory::Unknown)
                ->map(fn (DocumentCategory $category) => [
                    'value' => $category->value,
                    'label' => $category->label(),
                ])
                ->values(),
            'consentVersions' => [
                'privacy_notice_version' => config('careflow.consent.privacy_notice_version'),
                'enquiry_consent_version' => config('careflow.consent.enquiry_consent_version'),
                'health_data_consent_version' => config('careflow.consent.health_data_consent_version'),
                'ai_processing_consent_version' => config('careflow.consent.ai_processing_consent_version'),
            ],
            'initialLanguage' => $request->string('lang')->toString()
                ?: $settings->default_language
                ?: 'en',
        ]);
    }

    public function store(
        SubmitPatientIntakeRequest $request,
        Team $organization,
        SubmitPatientIntake $submitPatientIntake,
    ): RedirectResponse {
        /** @var array<int, UploadedFile> $files */
        $files = array_values($request->file('documents', []) ?: []);

        $result = $submitPatientIntake->handle(
            IntakeSubmissionData::fromValidatedRequest(
                team: $organization,
                validated: $request->validated(),
                uploadedFiles: $files,
                categories: $request->input('document_categories', []),
                sourceIp: $request->ip(),
                userAgent: $request->userAgent(),
            ),
        );

        return to_route('portal.intake.confirmation', [
            'organization' => $organization->slug,
            'reference' => $result['case']->reference,
            'token' => $result['portal_token'],
        ]);
    }

    public function confirmation(Request $request, Team $organization, string $reference): Response
    {
        $token = $request->string('token')->toString();

        abort_unless($token !== '', 404);

        return Inertia::render('portal/intake-confirmation', [
            'organization' => [
                'name' => $organization->organizationSetting?->legal_name ?: $organization->name,
                'slug' => $organization->slug,
            ],
            'reference' => $reference,
            'trackingUrl' => route('portal.cases.show', [
                'organization' => $organization->slug,
                'reference' => $reference,
                'token' => $token,
            ]),
        ]);
    }
}
