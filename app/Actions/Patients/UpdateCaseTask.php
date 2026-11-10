<?php

namespace App\Actions\Patients;

use App\Enums\CaseActivityType;
use App\Enums\CaseTaskPriority;
use App\Enums\CaseTaskStatus;
use App\Models\CaseTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateCaseTask
{
    public function __construct(
        private RecordCaseActivity $recordActivity,
    ) {}

    /**
     * @param  array{
     *     title?: string,
     *     description?: string|null,
     *     assigned_user_id?: int|null,
     *     priority?: string,
     *     due_at?: string|null
     * }  $data
     */
    public function handle(CaseTask $task, User $actor, array $data): CaseTask
    {
        return DB::transaction(function () use ($task, $actor, $data) {
            /** @var CaseTask $locked */
            $locked = CaseTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [CaseTaskStatus::Completed, CaseTaskStatus::Cancelled], true)) {
                throw new InvalidArgumentException('Completed or cancelled tasks cannot be edited.');
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
                'assigned_user_id' => array_key_exists('assigned_user_id', $data) ? $data['assigned_user_id'] : $locked->assigned_user_id,
                'priority' => isset($data['priority']) ? CaseTaskPriority::from($data['priority']) : $locked->priority,
                'due_at' => array_key_exists('due_at', $data) ? $data['due_at'] : $locked->due_at,
            ])->save();

            $case = $locked->case()->lockForUpdate()->firstOrFail();
            $case->forceFill(['last_activity_at' => now()])->save();

            $this->recordActivity->handle(
                case: $case,
                type: CaseActivityType::TaskUpdated,
                summary: sprintf('Task updated: %s', $locked->title),
                actor: $actor,
                properties: [
                    'task_uuid' => $locked->uuid,
                ],
            );

            return $locked->fresh(['assignedUser:id,name,email', 'createdBy:id,name']);
        });
    }
}
