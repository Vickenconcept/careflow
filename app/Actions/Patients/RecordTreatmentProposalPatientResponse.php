<?php

namespace App\Actions\Patients;

use App\Domains\Cases\CaseStatusTransitionMap;
use App\Domains\Proposals\TreatmentProposalTransitionMap;
use App\Domains\Workflows\WorkflowRunner;
use App\Enums\CaseActivityType;
use App\Enums\CaseStatus;
use App\Enums\TreatmentProposalPatientResponse;
use App\Enums\WorkflowKey;
use App\Exceptions\InvalidTreatmentProposalTransitionException;
use App\Models\PatientCase;
use App\Models\TreatmentProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordTreatmentProposalPatientResponse
{
    public function __construct(
        private TreatmentProposalTransitionMap $proposalTransitions,
        private CaseStatusTransitionMap $caseTransitions,
        private RecordCaseActivity $recordActivity,
        private WorkflowRunner $workflows,
    ) {}

    public function handle(
        TreatmentProposal $proposal,
        ?User $actor,
        TreatmentProposalPatientResponse $response,
        ?string $notes = null,
    ): TreatmentProposal {
        /** @var array{0: TreatmentProposal, 1: PatientCase, 2: bool} $result */
        $result = DB::transaction(function () use ($proposal, $actor, $response, $notes) {
            /** @var TreatmentProposal $locked */
            $locked = TreatmentProposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();

            $to = $response->resultingProposalStatus();

            if (! $this->proposalTransitions->canTransition($locked->status, $to)) {
                throw InvalidTreatmentProposalTransitionException::between($locked->status, $to);
            }

            $locked->update([
                'status' => $to,
                'patient_response' => $response,
                'patient_responded_at' => now(),
                'decision_notes' => $notes ?? $locked->decision_notes,
            ]);

            $case = $locked->case()->lockForUpdate()->firstOrFail();
            $targetCaseStatus = $response === TreatmentProposalPatientResponse::Accepted
                ? CaseStatus::AwaitingPatientResponse
                : CaseStatus::Declined;

            // Accepted proposals typically move into awaiting response / appointment planning.
            // Prefer AwaitingPatientResponse when currently ProposalSent.
            if ($response === TreatmentProposalPatientResponse::Accepted) {
                $targetCaseStatus = match ($case->status) {
                    CaseStatus::ProposalSent => CaseStatus::AwaitingPatientResponse,
                    default => $case->status,
                };
            }

            if (
                $case->status !== $targetCaseStatus
                && $this->caseTransitions->canTransition($case->status, $targetCaseStatus)
            ) {
                $from = $case->status;
                $case->status = $targetCaseStatus;

                $this->recordActivity->handle(
                    case: $case,
                    type: CaseActivityType::StatusChanged,
                    summary: sprintf('Status changed to %s after patient proposal response', $targetCaseStatus->label()),
                    actor: $actor,
                    properties: [
                        'from' => $from->value,
                        'to' => $targetCaseStatus->value,
                        'proposal_uuid' => $locked->uuid,
                    ],
                    patientVisible: true,
                );
            }

            $case->last_activity_at = now();
            $case->save();

            $this->recordActivity->handle(
                case: $case,
                type: CaseActivityType::TreatmentProposalPatientResponded,
                summary: sprintf(
                    'Patient %s treatment proposal %s',
                    $response->label(),
                    $locked->reference,
                ),
                actor: $actor,
                properties: [
                    'proposal_uuid' => $locked->uuid,
                    'proposal_reference' => $locked->reference,
                    'response' => $response->value,
                ],
                patientVisible: true,
            );

            return [
                $locked->fresh(['items']),
                $case->fresh(),
                $response === TreatmentProposalPatientResponse::Accepted,
            ];
        });

        [$proposal, $case, $accepted] = $result;

        if ($accepted) {
            $this->workflows->run(
                case: $case,
                workflowKey: WorkflowKey::TreatmentCoordination,
                idempotencyKey: 'treatment_coordination:'.$case->id.':'.$proposal->id,
                context: [
                    'proposal_uuid' => $proposal->uuid,
                    'trigger' => WorkflowKey::TreatmentCoordination->trigger(),
                ],
                // A patient answering in the portal has no staff account, so the
                // case coordinator owns the follow-up work.
                actor: $actor ?? $case->assignedCoordinator,
            );
        }

        return $proposal;
    }
}
