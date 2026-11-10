<?php

namespace App\Actions\Patients;

use App\Domains\Proposals\TreatmentProposalTransitionMap;
use App\Enums\CaseActivityType;
use App\Enums\TreatmentProposalStatus;
use App\Exceptions\InvalidTreatmentProposalTransitionException;
use App\Models\TreatmentProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DecideTreatmentProposal
{
    public function __construct(
        private TreatmentProposalTransitionMap $transitions,
        private RecordCaseActivity $recordActivity,
    ) {}

    public function handle(
        TreatmentProposal $proposal,
        User $actor,
        bool $approve,
        ?string $notes = null,
    ): TreatmentProposal {
        return DB::transaction(function () use ($proposal, $actor, $approve, $notes) {
            /** @var TreatmentProposal $locked */
            $locked = TreatmentProposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();

            $to = $approve
                ? TreatmentProposalStatus::Approved
                : TreatmentProposalStatus::Draft;

            if (! $this->transitions->canTransition($locked->status, $to)) {
                throw InvalidTreatmentProposalTransitionException::between($locked->status, $to);
            }

            if ($approve && $locked->clinical_recommendation_id === null) {
                // Administrative proposal may proceed, but warn via activity properties.
            }

            if ($approve && $locked->isExpired()) {
                throw new InvalidArgumentException('An expired proposal cannot be approved. Create a revised proposal.');
            }

            $locked->update([
                'status' => $to,
                'approved_at' => $approve ? now() : null,
                'approved_by_user_id' => $approve ? $actor->id : null,
                'decision_notes' => $notes,
                'submitted_for_approval_at' => $approve ? $locked->submitted_for_approval_at : null,
            ]);

            $case = $locked->case()->lockForUpdate()->firstOrFail();
            $case->forceFill(['last_activity_at' => now()])->save();

            $this->recordActivity->handle(
                case: $case,
                type: CaseActivityType::TreatmentProposalDecided,
                summary: sprintf(
                    'Treatment proposal %s %s',
                    $locked->reference,
                    $approve ? 'approved' : 'returned to draft',
                ),
                actor: $actor,
                properties: [
                    'proposal_uuid' => $locked->uuid,
                    'proposal_reference' => $locked->reference,
                    'approved' => $approve,
                ],
            );

            return $locked->fresh(['items', 'approvedBy:id,name,email']);
        });
    }
}
