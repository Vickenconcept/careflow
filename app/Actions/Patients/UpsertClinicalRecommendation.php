<?php

namespace App\Actions\Patients;

use App\Enums\CaseActivityType;
use App\Enums\ClinicalRecommendationStatus;
use App\Models\ClinicalRecommendation;
use App\Models\PatientCase;
use App\Models\PatientDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UpsertClinicalRecommendation
{
    public function __construct(
        private EnsureOpenDoctorReview $ensureOpenDoctorReview,
        private RecordCaseActivity $recordActivity,
    ) {}

    /**
     * @param  list<string>  $supportingDocumentUuids
     */
    public function handle(
        PatientCase $case,
        User $author,
        string $recommendationText,
        array $supportingDocumentUuids = [],
        bool $submitForApproval = false,
        ?ClinicalRecommendation $recommendation = null,
    ): ClinicalRecommendation {
        return DB::transaction(function () use (
            $case,
            $author,
            $recommendationText,
            $supportingDocumentUuids,
            $submitForApproval,
            $recommendation,
        ) {
            $review = $this->ensureOpenDoctorReview->handle($case, $author);
            $documentUuids = $this->assertSupportingDocumentsBelongToCase($case, $supportingDocumentUuids);

            if ($recommendation !== null) {
                abort_unless($recommendation->patient_case_id === $case->id, 404);

                if (! $recommendation->status->isEditable()) {
                    throw new InvalidArgumentException('This clinical recommendation can no longer be edited.');
                }

                if ($recommendation->is_ai_generated) {
                    throw new InvalidArgumentException('AI-generated content cannot become a clinical recommendation.');
                }

                $recommendation->update([
                    'doctor_review_id' => $review->id,
                    'recommendation_text' => $recommendationText,
                    'supporting_document_uuids' => $documentUuids,
                    'status' => $submitForApproval
                        ? ClinicalRecommendationStatus::AwaitingApproval
                        : ClinicalRecommendationStatus::Draft,
                ]);

                $saved = $recommendation->fresh();
            } else {
                ClinicalRecommendation::query()
                    ->where('patient_case_id', $case->id)
                    ->where('is_current', true)
                    ->whereIn('status', [
                        ClinicalRecommendationStatus::Draft->value,
                        ClinicalRecommendationStatus::AwaitingApproval->value,
                        ClinicalRecommendationStatus::Rejected->value,
                    ])
                    ->update([
                        'is_current' => false,
                        'status' => ClinicalRecommendationStatus::Superseded,
                    ]);

                $nextVersion = ((int) ClinicalRecommendation::query()
                    ->where('patient_case_id', $case->id)
                    ->max('version')) + 1;

                $saved = ClinicalRecommendation::query()->create([
                    'team_id' => $case->team_id,
                    'patient_case_id' => $case->id,
                    'doctor_review_id' => $review->id,
                    'author_user_id' => $author->id,
                    'uuid' => (string) Str::uuid(),
                    'version' => max(1, $nextVersion),
                    'status' => $submitForApproval
                        ? ClinicalRecommendationStatus::AwaitingApproval
                        : ClinicalRecommendationStatus::Draft,
                    'recommendation_text' => $recommendationText,
                    'supporting_document_uuids' => $documentUuids,
                    'is_ai_generated' => false,
                    'is_current' => true,
                ]);
            }

            $case->forceFill(['last_activity_at' => now()])->save();

            $this->recordActivity->handle(
                case: $case,
                type: CaseActivityType::ClinicalRecommendationUpdated,
                summary: $submitForApproval
                    ? 'Clinical recommendation submitted for approval'
                    : 'Clinical recommendation draft saved',
                actor: $author,
                properties: [
                    'recommendation_uuid' => $saved->uuid,
                    'version' => $saved->version,
                    'status' => $saved->status->value,
                ],
            );

            return $saved;
        });
    }

    /**
     * @param  list<string>  $uuids
     * @return list<string>
     */
    protected function assertSupportingDocumentsBelongToCase(PatientCase $case, array $uuids): array
    {
        $unique = array_values(array_unique(array_filter($uuids)));

        if ($unique === []) {
            return [];
        }

        $count = PatientDocument::query()
            ->where('patient_case_id', $case->id)
            ->whereIn('uuid', $unique)
            ->count();

        if ($count !== count($unique)) {
            throw new InvalidArgumentException('Supporting documents must belong to this case.');
        }

        return $unique;
    }
}
