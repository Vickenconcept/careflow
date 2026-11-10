<?php

namespace App\Notifications\Patients;

use App\Models\PatientCase;
use App\Support\CareFlowMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewEnquiryStaffNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PatientCase $case)
    {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $case = $this->case->loadMissing(['team.organizationSetting', 'patient', 'department', 'enquiry']);
        $team = $case->team;
        $caseUrl = route('organizations.cases.show', [
            'current_team' => $team,
            'case' => $case,
        ]);

        return CareFlowMail::message(
            subject: sprintf('New patient enquiry · %s', $case->reference),
            title: 'A new enquiry needs attention',
            greeting: sprintf('Hello %s,', $notifiable->name ?? 'team'),
            introLines: [
                sprintf(
                    '<strong>%s</strong> just submitted an international patient enquiry. Please review and assign the case.',
                    e($case->patient?->fullName() ?? 'A patient'),
                ),
                e((string) str($case->enquiry?->reason_for_contact)->limit(180)),
            ],
            actionText: 'Open case',
            actionUrl: $caseUrl,
            meta: array_filter([
                'Case reference' => $case->reference,
                'Patient' => $case->patient?->fullName(),
                'Email' => $case->patient?->email,
                'Department' => $case->department?->name,
                'Status' => $case->status->label(),
            ]),
            outroLines: [
                'Tip: from the Cases table you can open Manage to assign a coordinator or update status without leaving the list.',
            ],
            team: $team,
            eyebrow: 'Coordinator alert',
            preheader: sprintf('New enquiry %s from %s', $case->reference, $case->patient?->fullName() ?? 'patient'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'case_id' => $this->case->id,
            'reference' => $this->case->reference,
        ];
    }
}
