<?php

namespace App\Listeners\Patients;

use App\Events\Patients\FollowUpScheduled;
use App\Models\PatientCase;
use App\Models\PatientFollowUp;
use App\Notifications\Patients\FollowUpScheduledNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendFollowUpScheduledNotifications implements ShouldQueue
{
    public function handle(FollowUpScheduled $event): void
    {
        $followUp = PatientFollowUp::query()
            ->with('assignedUser:id,name,email')
            ->find($event->followUpId);

        $case = PatientCase::query()
            ->with(['patient', 'team.organizationSetting'])
            ->find($event->caseId);

        if ($followUp === null || $case === null || $followUp->assignedUser === null) {
            return;
        }

        $followUp->assignedUser->notify(new FollowUpScheduledNotification($case, $followUp));
    }
}
