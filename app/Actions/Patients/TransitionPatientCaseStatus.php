<?php

namespace App\Actions\Patients;

use App\Domains\Cases\CaseStatusTransitionMap;
use App\Domains\Workflows\WorkflowRunner;
use App\Enums\CaseActivityType;
use App\Enums\CaseStatus;
use App\Enums\WorkflowKey;
use App\Events\Patients\CaseStatusUpdated;
use App\Exceptions\InvalidCaseStatusTransitionException;
use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransitionPatientCaseStatus
{
    public function __construct(
        private CaseStatusTransitionMap $transitions,
        private RecordCaseActivity $recordActivity,
        private WorkflowRunner $workflows,
    ) {}

    public function handle(
        PatientCase $case,
        CaseStatus $to,
        User $actor,
        ?string $note = null,
    ): PatientCase {
        $fromStatus = null;
        $patientVisible = false;

        $result = DB::transaction(function () use ($case, $to, $actor, $note, &$fromStatus, &$patientVisible) {
            /** @var PatientCase $locked */
            $locked = PatientCase::query()
                ->whereKey($case->id)
                ->lockForUpdate()
                ->firstOrFail();

            $from = $locked->status;
            $fromStatus = $from;

            if (! $this->transitions->canTransition($from, $to)) {
                throw InvalidCaseStatusTransitionException::between($from, $to);
            }

            $this->transitions->assertPrerequisites($locked, $to);

            $patientVisible = in_array($to, [
                CaseStatus::AwaitingDocuments,
                CaseStatus::AdditionalInformationRequired,
                CaseStatus::ProposalSent,
                CaseStatus::AwaitingPatientResponse,
                CaseStatus::Closed,
                CaseStatus::Declined,
            ], true);

            $locked->forceFill([
                'status' => $to,
                'last_activity_at' => now(),
            ])->save();

            $this->recordActivity->handle(
                case: $locked,
                type: CaseActivityType::StatusChanged,
                summary: sprintf('Status changed from %s to %s', $from->label(), $to->label()),
                actor: $actor,
                properties: [
                    'from' => $from->value,
                    'to' => $to->value,
                    'note' => $note,
                ],
                patientVisible: $patientVisible,
            );

            return $locked->fresh();
        });

        if ($to === CaseStatus::TreatmentCompleted) {
            $this->workflows->run(
                case: $result,
                workflowKey: WorkflowKey::PostTreatmentFollowUp,
                idempotencyKey: 'post_treatment_follow_up:'.$result->id,
                context: [
                    'trigger' => WorkflowKey::PostTreatmentFollowUp->trigger(),
                ],
                actor: $actor,
            );
        }

        if ($fromStatus instanceof CaseStatus) {
            event(new CaseStatusUpdated(
                caseId: $result->id,
                from: $fromStatus->value,
                to: $to->value,
                actorUserId: $actor->id,
                note: $note,
                patientVisible: $patientVisible,
            ));
        }

        return $result;
    }
}
