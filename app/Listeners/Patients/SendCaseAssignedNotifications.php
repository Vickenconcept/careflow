<?php

namespace App\Listeners\Patients;

use App\Events\Patients\CaseAssigned;
use App\Models\PatientCase;
use App\Models\User;
use App\Notifications\Patients\CaseAssignedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendCaseAssignedNotifications implements ShouldQueue
{
    public function handle(CaseAssigned $event): void
    {
        $case = PatientCase::query()
            ->with(['patient', 'team.organizationSetting', 'department'])
            ->find($event->caseId);

        $actor = User::query()->find($event->actorUserId);

        if ($case === null || $actor === null) {
            return;
        }

        if ($event->coordinatorUserId !== null) {
            $coordinator = User::query()->find($event->coordinatorUserId);

            if ($coordinator !== null && $coordinator->id !== $actor->id) {
                $coordinator->notify(new CaseAssignedNotification(
                    case: $case,
                    roleLabel: 'Coordinator',
                    assignedBy: $actor,
                ));
            }
        }

        if ($event->doctorUserId !== null) {
            $doctor = User::query()->find($event->doctorUserId);

            if ($doctor !== null && $doctor->id !== $actor->id) {
                $doctor->notify(new CaseAssignedNotification(
                    case: $case,
                    roleLabel: 'Doctor',
                    assignedBy: $actor,
                ));
            }
        }
    }
}
