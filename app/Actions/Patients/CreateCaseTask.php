<?php

namespace App\Actions\Patients;

use App\Enums\CaseActivityType;
use App\Enums\CaseTaskPriority;
use App\Enums\CaseTaskStatus;
use App\Models\CaseTask;
use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateCaseTask
{
    public function __construct(
        private RecordCaseActivity $recordActivity,
    ) {}

    /**
     * @param  array{
     *     title: string,
     *     description?: string|null,
     *     assigned_user_id?: int|null,
     *     priority?: string,
     *     due_at?: string|null
     * }  $data
     */
    public function handle(PatientCase $case, User $actor, array $data): CaseTask
    {
        return DB::transaction(function () use ($case, $actor, $data) {
            /** @var PatientCase $locked */
            $locked = PatientCase::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();

            $assigneeId = $data['assigned_user_id'] ?? null;
            if ($assigneeId !== null) {
                $assignee = User::query()->findOrFail($assigneeId);
                if (! $assignee->belongsToTeam($locked->team)) {
                    throw new InvalidArgumentException('Assigned user must belong to this organization.');
                }
            }

            $task = CaseTask::query()->create([
                'team_id' => $locked->team_id,
                'patient_case_id' => $locked->id,
                'assigned_user_id' => $assigneeId,
                'created_by_user_id' => $actor->id,
                'uuid' => (string) Str::uuid(),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => CaseTaskStatus::Open,
                'priority' => CaseTaskPriority::from($data['priority'] ?? CaseTaskPriority::Normal->value),
                'due_at' => $data['due_at'] ?? null,
                'is_demo' => $locked->is_demo,
            ]);

            $locked->forceFill(['last_activity_at' => now()])->save();

            $this->recordActivity->handle(
                case: $locked,
                type: CaseActivityType::TaskCreated,
                summary: sprintf('Task created: %s', $task->title),
                actor: $actor,
                properties: [
                    'task_uuid' => $task->uuid,
                    'priority' => $task->priority->value,
                ],
            );

            return $task->fresh(['assignedUser:id,name,email', 'createdBy:id,name']);
        });
    }
}
