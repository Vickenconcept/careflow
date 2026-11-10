<?php

namespace App\Listeners\Patients;

use App\Enums\TeamRole;
use App\Events\Patients\PatientEnquirySubmitted;
use App\Models\PatientCase;
use App\Notifications\Patients\NewEnquiryStaffNotification;
use App\Notifications\Patients\PatientEnquiryReceivedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendPatientEnquiryNotifications implements ShouldQueue
{
    public function handle(PatientEnquirySubmitted $event): void
    {
        $case = PatientCase::query()
            ->with(['patient', 'team.organizationSetting', 'department', 'enquiry'])
            ->find($event->caseId);

        if ($case === null || $case->patient?->email === null) {
            return;
        }

        if ($event->portalToken !== '') {
            Notification::route('mail', $case->patient->email)
                ->notify(new PatientEnquiryReceivedNotification($case, $event->portalToken));
        }

        $staff = $case->team->members()
            ->wherePivotIn('role', [
                TeamRole::Owner->value,
                TeamRole::Admin->value,
                TeamRole::Coordinator->value,
            ])
            ->get();

        Notification::send($staff, new NewEnquiryStaffNotification($case));
    }
}
