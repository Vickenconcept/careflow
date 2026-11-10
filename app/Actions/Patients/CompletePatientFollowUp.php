<?php

namespace App\Actions\Patients;

use App\Enums\CaseActivityType;
use App\Enums\FollowUpStatus;
use App\Models\PatientFollowUp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CompletePatientFollowUp
{
    public function __construct(
        private RecordCaseActivity $recordActivity,
    ) {}

    public function handle(PatientFollowUp $followUp, User $actor, ?string $outcomeNotes = null, bool $cancel = false): PatientFollowUp
    {
        return DB::transaction(function () use ($followUp, $actor, $outcomeNotes, $cancel) {
            /** @var PatientFollowUp $locked */
            $locked = PatientFollowUp::query()->whereKey($followUp->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [FollowUpStatus::Completed, FollowUpStatus::Cancelled], true)) {
                throw new InvalidArgumentException('This follow-up is already closed.');
            }

            $status = $cancel ? FollowUpStatus::Cancelled : FollowUpStatus::Completed;

            $locked->forceFill([
                'status' => $status,
                'completed_at' => now(),
                'completed_by_user_id' => $actor->id,
                'outcome_notes' => $outcomeNotes,
            ])->save();

            $case = $locked->case()->lockForUpdate()->firstOrFail();
            $case->forceFill(['last_activity_at' => now()])->save();

            $this->recordActivity->handle(
                case: $case,
                type: CaseActivityType::FollowUpCompleted,
                summary: sprintf('Follow-up %s: %s', $status->label(), $locked->title),
                actor: $actor,
                properties: [
                    'follow_up_uuid' => $locked->uuid,
                    'status' => $status->value,
                ],
            );

            return $locked->fresh(['assignedUser:id,name,email', 'completedBy:id,name']);
        });
    }
}
