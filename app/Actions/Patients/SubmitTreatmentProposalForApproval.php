<?php

namespace App\Actions\Patients;

use App\Domains\Proposals\TreatmentProposalTransitionMap;
use App\Enums\CaseActivityType;
use App\Enums\TreatmentProposalStatus;
use App\Exceptions\InvalidTreatmentProposalTransitionException;
use App\Models\TreatmentProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubmitTreatmentProposalForApproval
{
    public function __construct(
        private TreatmentProposalTransitionMap $transitions,
        private RecordCaseActivity $recordActivity,
    ) {}

    public function handle(TreatmentProposal $proposal, User $actor): TreatmentProposal
    {
        return DB::transaction(function () use ($proposal, $actor) {
            /** @var TreatmentProposal $locked */
            $locked = TreatmentProposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();

            $to = TreatmentProposalStatus::AwaitingClinicalApproval;

            if (! $this->transitions->canTransition($locked->status, $to)) {
                throw InvalidTreatmentProposalTransitionException::between($locked->status, $to);
            }

            if (blank($locked->proposed_procedure)) {
                throw InvalidTreatmentProposalTransitionException::missingPrerequisite(
                    'A proposed procedure is required before clinical approval.',
                );
            }

            $locked->update([
                'status' => $to,
                'submitted_for_approval_at' => now(),
            ]);

            $case = $locked->case()->lockForUpdate()->firstOrFail();
            $case->forceFill(['last_activity_at' => now()])->save();

            $this->recordActivity->handle(
                case: $case,
                type: CaseActivityType::TreatmentProposalSubmitted,
                summary: sprintf('Treatment proposal %s submitted for clinical approval', $locked->reference),
                actor: $actor,
                properties: [
                    'proposal_uuid' => $locked->uuid,
                    'proposal_reference' => $locked->reference,
                ],
            );

            return $locked->fresh(['items']);
        });
    }
}
