<?php

namespace App\Actions\Patients;

use App\Enums\CaseActivityType;
use App\Enums\FollowUpStatus;
use App\Enums\FollowUpType;
use App\Models\PatientFollowUp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdatePatientFollowUp
{
    public function __construct(
        private RecordCaseActivity $recordActivity,
    ) {}

    /**
     * @param  array{
     *     title?: string,
     *     description?: string|null,
     *     follow_up_type?: string,
     *     due_at?: string,
     *     assigned_user_id?: int|null,
     *     status?: string
     * }  $data
     */
    public function handle(PatientFollowUp $followUp, User $actor, array $data): PatientFollowUp
    {
        return DB::transaction(function () use ($followUp, $actor, $data) {
            /** @var PatientFollowUp $locked */
            $locked = PatientFollowUp::query()->whereKey($followUp->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [FollowUpStatus::Completed, FollowUpStatus::Cancelled], true)) {
                throw new InvalidArgumentException('Completed or cancelled follow-ups cannot be edited.');
            }

            if (array_key_exists('assigned_user_id', $data) && $data['assigned_user_id'] !== null) {
                $assignee = User::query()->findOrFail($data['assigned_user_id']);
                if (! $assignee->belongsToTeam($locked->team)) {
                    throw new InvalidArgumentException('Assigned user must belong to this organization.');
                }
            }

            $locked->fill([
                'title' => $data['title'] ?? $locked->title,
                'description' => array_key_exists('description', $data) ? $data['description'] : $locked->description,
                'follow_up_type' => isset($data['follow_up_type'])
                    ? FollowUpType::from($data['follow_up_type'])
                    : $locked->follow_up_type,
                'due_at' => $data['due_at'] ?? $locked->due_at,
                'assigned_user_id' => array_key_exists('assigned_user_id', $data) ? $data['assigned_user_id'] : $locked->assigned_user_id,
                'status' => isset($data['status']) ? FollowUpStatus::from($data['status']) : $locked->status,
            ]);

            if ($locked->isDirty('due_at')) {
                // A new date gets its own reminder (and patient reminder draft) when it comes round.
                $locked->reminded_at = null;
            }

            $locked->save();

            $case = $locked->case()->lockForUpdate()->firstOrFail();
            $case->forceFill(['last_activity_at' => now()])->save();

            $this->recordActivity->handle(
                case: $case,
                type: CaseActivityType::FollowUpUpdated,
                summary: sprintf('Follow-up updated: %s', $locked->title),
                actor: $actor,
                properties: [
                    'follow_up_uuid' => $locked->uuid,
                    'status' => $locked->status->value,
                ],
            );

            return $locked->fresh(['assignedUser:id,name,email']);
        });
    }
}
