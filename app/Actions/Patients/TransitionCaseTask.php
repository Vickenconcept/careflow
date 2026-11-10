<?php

namespace App\Actions\Patients;

use App\Domains\Tasks\CaseTaskTransitionMap;
use App\Enums\CaseActivityType;
use App\Enums\CaseTaskStatus;
use App\Models\CaseTask;
use App\Models\CaseTaskComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransitionCaseTask
{
    public function __construct(
        private CaseTaskTransitionMap $transitions,
        private RecordCaseActivity $recordActivity,
    ) {}

    /**
     * @param  array{status: string, comment?: string|null}  $data
     */
    public function handle(CaseTask $task, User $actor, array $data): CaseTask
    {
        return DB::transaction(function () use ($task, $actor, $data) {
            /** @var CaseTask $locked */
            $locked = CaseTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $to = CaseTaskStatus::from($data['status']);

            if (! $this->transitions->canTransition($locked->status, $to)) {
                throw new InvalidArgumentException(sprintf(
                    'Cannot transition task from %s to %s.',
                    $locked->status->label(),
                    $to->label(),
                ));
            }

            $from = $locked->status;

            $locked->forceFill([
                'status' => $to,
                'completed_at' => $to === CaseTaskStatus::Completed ? now() : null,
                'completed_by_user_id' => $to === CaseTaskStatus::Completed ? $actor->id : null,
            ])->save();

            if (! blank($data['comment'] ?? null)) {
                CaseTaskComment::query()->create([
                    'team_id' => $locked->team_id,
                    'case_task_id' => $locked->id,
                    'author_user_id' => $actor->id,
                    'body' => $data['comment'],
                ]);
            }

            $case = $locked->case()->lockForUpdate()->firstOrFail();
            $case->forceFill(['last_activity_at' => now()])->save();

            $this->recordActivity->handle(
                case: $case,
                type: CaseActivityType::TaskStatusChanged,
                summary: sprintf('Task %s → %s', $from->label(), $to->label()),
                actor: $actor,
                properties: [
                    'task_uuid' => $locked->uuid,
                    'from' => $from->value,
                    'to' => $to->value,
                ],
            );

            return $locked->fresh(['assignedUser:id,name,email', 'createdBy:id,name', 'comments.author:id,name']);
        });
    }
}
