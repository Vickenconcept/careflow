<?php

namespace App\Listeners\Patients;

use App\Events\Patients\CaseStatusUpdated;
use App\Models\PatientCase;
use App\Models\User;
use App\Notifications\Patients\CaseStatusUpdatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendCaseStatusUpdatedNotifications implements ShouldQueue
{
    public function handle(CaseStatusUpdated $event): void
    {
        $case = PatientCase::query()
            ->with([
                'patient',
                'team.organizationSetting',
                'department',
                'assignedCoordinator:id,name,email',
                'assignedDoctor:id,name,email',
            ])
            ->find($event->caseId);

        if ($case === null) {
            return;
        }

        $staffRecipients = collect([
            $case->assignedCoordinator,
            $case->assignedDoctor,
        ])
            ->filter()
            ->unique('id')
            ->reject(fn (User $user) => $user->id === $event->actorUserId)
            ->values();

        foreach ($staffRecipients as $staff) {
            $staff->notify(new CaseStatusUpdatedNotification(
                case: $case,
                from: $event->fromStatus(),
                to: $event->toStatus(),
                audience: 'staff',
                note: $event->note,
            ));
        }

        if ($event->patientVisible && filled($case->patient?->email)) {
            Notification::route('mail', $case->patient->email)
                ->notify(new CaseStatusUpdatedNotification(
                    case: $case,
                    from: $event->fromStatus(),
                    to: $event->toStatus(),
                    audience: 'patient',
                    note: null,
                ));
        }
    }
}
