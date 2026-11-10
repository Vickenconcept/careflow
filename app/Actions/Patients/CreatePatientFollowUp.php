<?php

namespace App\Actions\Patients;

use App\Enums\CaseActivityType;
use App\Enums\FollowUpStatus;
use App\Enums\FollowUpType;
use App\Events\Patients\FollowUpScheduled;
use App\Models\PatientCase;
use App\Models\PatientFollowUp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreatePatientFollowUp
{
    public function __construct(
        private RecordCaseActivity $recordActivity,
    ) {}

    /**
     * @param  array{
     *     title: string,
     *     description?: string|null,
     *     follow_up_type: string,
     *     due_at: string,
     *     assigned_user_id?: int|null,
     *     status?: string
     * }  $data
     */
    public function handle(PatientCase $case, User $actor, array $data): PatientFollowUp
    {
        $followUp = DB::transaction(function () use ($case, $actor, $data) {
            /** @var PatientCase $locked */
            $locked = PatientCase::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();

            $assigneeId = $data['assigned_user_id'] ?? $locked->assigned_coordinator_id;
            if ($assigneeId !== null) {
                $assignee = User::query()->findOrFail($assigneeId);
                if (! $assignee->belongsToTeam($locked->team)) {
                    throw new InvalidArgumentException('Assigned user must belong to this organization.');
                }
            }

            $followUp = PatientFollowUp::query()->create([
                'team_id' => $locked->team_id,
                'patient_case_id' => $locked->id,
                'assigned_user_id' => $assigneeId,
                'created_by_user_id' => $actor->id,
                'uuid' => (string) Str::uuid(),
                'follow_up_type' => FollowUpType::from($data['follow_up_type']),
                'status' => FollowUpStatus::from($data['status'] ?? FollowUpStatus::Scheduled->value),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'due_at' => $data['due_at'],
                'is_demo' => $locked->is_demo,
            ]);

            $locked->forceFill(['last_activity_at' => now()])->save();

            $this->recordActivity->handle(
                case: $locked,
                type: CaseActivityType::FollowUpScheduled,
                summary: sprintf('Follow-up scheduled: %s', $followUp->title),
                actor: $actor,
                properties: [
                    'follow_up_uuid' => $followUp->uuid,
                    'due_at' => $followUp->due_at?->toIso8601String(),
                    'type' => $followUp->follow_up_type->value,
                ],
            );

            return $followUp->fresh(['assignedUser:id,name,email']);
        });

        event(new FollowUpScheduled(
            followUpId: $followUp->id,
            caseId: $followUp->patient_case_id,
        ));

        return $followUp;
    }
}
