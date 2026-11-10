<?php

namespace App\Actions\Patients;

use App\Domains\Cases\CaseStatusTransitionMap;
use App\Domains\Workflows\WorkflowRunner;
use App\Enums\CaseActivityType;
use App\Enums\CaseStatus;
use App\Enums\DoctorReviewOutcome;
use App\Enums\WorkflowKey;
use App\Models\DoctorReview;
use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubmitDoctorReview
{
    public function __construct(
        private EnsureOpenDoctorReview $ensureOpenDoctorReview,
        private CaseStatusTransitionMap $transitions,
        private RecordCaseActivity $recordActivity,
        private WorkflowRunner $workflows,
    ) {}

    public function handle(
        PatientCase $case,
        User $reviewer,
        DoctorReviewOutcome $outcome,
        string $findings,
        ?string $additionalInformationRequest = null,
    ): DoctorReview {
        if ($outcome === DoctorReviewOutcome::NeedMoreInformation && blank($additionalInformationRequest)) {
            throw new InvalidArgumentException('Describe the additional information required.');
        }

        /** @var array{0: DoctorReview, 1: PatientCase, 2: bool} $result */
        $result = DB::transaction(function () use ($case, $reviewer, $outcome, $findings, $additionalInformationRequest) {
            /** @var PatientCase $locked */
            $locked = PatientCase::query()
                ->whereKey($case->id)
                ->lockForUpdate()
                ->firstOrFail();

            $review = $this->ensureOpenDoctorReview->handle($locked, $reviewer);

            if (! $review->status->isOpen()) {
                throw new InvalidArgumentException('This doctor review is already closed.');
            }

            $review->update([
                'status' => $outcome->resultingReviewStatus(),
                'outcome' => $outcome,
                'findings' => $findings,
                'additional_information_request' => $outcome === DoctorReviewOutcome::NeedMoreInformation
                    ? $additionalInformationRequest
                    : null,
                'completed_at' => now(),
            ]);

            $targetStatus = $outcome->resultingCaseStatus();
            $statusTransitioned = false;

            if ($locked->status !== $targetStatus && $this->transitions->canTransition($locked->status, $targetStatus)) {
                $this->transitions->assertPrerequisites($locked, $targetStatus);

                $from = $locked->status;
                $locked->status = $targetStatus;
                $statusTransitioned = true;

                $this->recordActivity->handle(
                    case: $locked,
                    type: CaseActivityType::StatusChanged,
                    summary: sprintf('Status changed to %s after doctor review', $targetStatus->label()),
                    actor: $reviewer,
                    properties: [
                        'from' => $from->value,
                        'to' => $targetStatus->value,
                        'doctor_review_uuid' => $review->uuid,
                    ],
                    patientVisible: $targetStatus === CaseStatus::AdditionalInformationRequired,
                );
            }

            $this->recordActivity->handle(
                case: $locked,
                type: CaseActivityType::DoctorReviewSubmitted,
                summary: sprintf('Doctor review submitted (%s)', $outcome->label()),
                actor: $reviewer,
                properties: [
                    'doctor_review_uuid' => $review->uuid,
                    'outcome' => $outcome->value,
                    'requested_status' => $targetStatus->value,
                    'status_transitioned' => $statusTransitioned,
                    'findings_present' => filled($findings),
                ],
            );

            $locked->last_activity_at = now();
            $locked->save();

            return [
                $review->fresh(['reviewer:id,name,email']),
                $locked->fresh(),
                $locked->status === CaseStatus::AdditionalInformationRequired,
            ];
        });

        [$review, $freshCase, $shouldRunAdditionalDocumentsWorkflow] = $result;

        if ($shouldRunAdditionalDocumentsWorkflow) {
            $this->workflows->run(
                case: $freshCase,
                workflowKey: WorkflowKey::AdditionalDocuments,
                idempotencyKey: 'additional_documents:'.$freshCase->id.':'.$review->id,
                context: [
                    'doctor_review_uuid' => $review->uuid,
                    'trigger' => WorkflowKey::AdditionalDocuments->trigger(),
                ],
                actor: $reviewer,
            );
        }

        return $review;
    }
}
