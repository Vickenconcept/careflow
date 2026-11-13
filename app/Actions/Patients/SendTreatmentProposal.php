<?php

namespace App\Actions\Patients;

use App\Domains\Cases\CaseStatusTransitionMap;
use App\Domains\Proposals\TreatmentProposalTransitionMap;
use App\Enums\CaseActivityType;
use App\Enums\CaseStatus;
use App\Enums\MessageTemplateKey;
use App\Enums\TreatmentProposalStatus;
use App\Exceptions\InvalidTreatmentProposalTransitionException;
use App\Models\TreatmentProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SendTreatmentProposal
{
    public function __construct(
        private TreatmentProposalTransitionMap $proposalTransitions,
        private CaseStatusTransitionMap $caseTransitions,
        private RecordCaseActivity $recordActivity,
        private NotifyPatient $notifyPatient,
    ) {}

    /**
     * Mark the proposal as sent and email the patient a link to review, accept or decline it.
     */
    public function handle(TreatmentProposal $proposal, User $actor): TreatmentProposal
    {
        $sent = DB::transaction(function () use ($proposal, $actor) {
            /** @var TreatmentProposal $locked */
            $locked = TreatmentProposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();

            $to = TreatmentProposalStatus::SentToPatient;

            if (! $this->proposalTransitions->canTransition($locked->status, $to)) {
                throw InvalidTreatmentProposalTransitionException::between($locked->status, $to);
            }

            if ($locked->isExpired()) {
                throw new InvalidArgumentException('Cannot send an expired proposal. Create a revised proposal.');
            }

            if ($locked->approved_by_user_id === null || $locked->approved_at === null) {
                throw InvalidTreatmentProposalTransitionException::missingPrerequisite(
                    'A clinician must approve the proposal before it can be sent to the patient.',
                );
            }

            $locked->update([
                'status' => $to,
                'sent_at' => now(),
                'sent_by_user_id' => $actor->id,
            ]);

            $case = $locked->case()->lockForUpdate()->firstOrFail();

            if (
                $case->status !== CaseStatus::ProposalSent
                && $this->caseTransitions->canTransition($case->status, CaseStatus::ProposalSent)
            ) {
                $from = $case->status;
                $case->status = CaseStatus::ProposalSent;

                $this->recordActivity->handle(
                    case: $case,
                    type: CaseActivityType::StatusChanged,
                    summary: 'Status changed to Proposal Sent after proposal delivery',
                    actor: $actor,
                    properties: [
                        'from' => $from->value,
                        'to' => CaseStatus::ProposalSent->value,
                        'proposal_uuid' => $locked->uuid,
                    ],
                    patientVisible: true,
                );
            }

            $case->last_activity_at = now();
            $case->save();

            $this->recordActivity->handle(
                case: $case,
                type: CaseActivityType::TreatmentProposalSent,
                summary: sprintf('Treatment proposal %s sent to patient', $locked->reference),
                actor: $actor,
                properties: [
                    'proposal_uuid' => $locked->uuid,
                    'proposal_reference' => $locked->reference,
                ],
                patientVisible: true,
            );

            return $locked->fresh(['items']);
        });

        $this->notifyPatient->handle(
            case: $sent->case,
            key: MessageTemplateKey::TreatmentProposalAvailable,
            actor: $actor,
            sendNow: true,
        );

        return $sent;
    }
}
