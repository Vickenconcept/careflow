<?php

namespace App\Notifications\Patients;

use App\Enums\CaseStatus;
use App\Models\PatientCase;
use App\Support\CareFlowMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CaseStatusUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PatientCase $case,
        public CaseStatus $from,
        public CaseStatus $to,
        public string $audience,
        public ?string $note = null,
        public ?string $portalToken = null,
    ) {
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
        $case = $this->case->loadMissing(['team.organizationSetting', 'patient', 'department']);
        $team = $case->team;
        $isPatient = $this->audience === 'patient';

        $actionUrl = null;
        $actionText = null;

        if ($isPatient && filled($this->portalToken)) {
            $actionText = 'View tracking page';
            $actionUrl = route('portal.cases.show', [
                'organization' => $team->slug,
                'reference' => $case->reference,
                'token' => $this->portalToken,
            ]);
        } elseif (! $isPatient) {
            $actionText = 'Open case';
            $actionUrl = route('organizations.cases.show', [
                'current_team' => $team,
                'case' => $case,
            ]);
        }

        $greetingName = $isPatient
            ? ($case->patient?->first_name ?: 'there')
            : ($notifiable->name ?? 'team');

        $intro = $isPatient
            ? sprintf(
                'There is an update on your care case <strong>%s</strong> with %s.',
                e($case->reference),
                e($team->organizationSetting?->legal_name ?: $team->name),
            )
            : sprintf(
                'Case <strong>%s</strong> moved from <strong>%s</strong> to <strong>%s</strong>.',
                e($case->reference),
                e($this->from->label()),
                e($this->to->label()),
            );

        $patientVisibleCopy = match ($this->to) {
            CaseStatus::AwaitingDocuments => 'The hospital is waiting for additional documents from you.',
            CaseStatus::AdditionalInformationRequired => 'The clinical team needs a little more information before continuing.',
            CaseStatus::ProposalSent, CaseStatus::AwaitingPatientResponse => 'A treatment proposal is ready for your review.',
            CaseStatus::Closed => 'This enquiry has been closed. Contact the hospital if you still need assistance.',
            CaseStatus::Declined => 'The hospital was unable to proceed with this enquiry at this time.',
            default => 'You can open your secure tracking page for the latest patient-safe status.',
        };

        return CareFlowMail::message(
            subject: $isPatient
                ? sprintf('Update on your case · %s', $case->reference)
                : sprintf('Status updated · %s', $case->reference),
            title: $isPatient ? 'Your case was updated' : 'Case status changed',
            greeting: sprintf('Hello %s,', $greetingName),
            introLines: array_values(array_filter([
                $intro,
                $isPatient ? $patientVisibleCopy : null,
                $this->note ? 'Note: '.e($this->note) : null,
            ])),
            actionText: $actionText,
            actionUrl: $actionUrl,
            meta: array_filter([
                'Case reference' => $case->reference,
                'Previous status' => $isPatient ? $this->from->patientLabel() : $this->from->label(),
                'New status' => $isPatient ? $this->to->patientLabel() : $this->to->label(),
                'Patient' => $isPatient ? null : $case->patient?->fullName(),
            ]),
            outroLines: [
                $isPatient
                    ? ($this->portalToken
                        ? 'Only information approved for patients is shown on the tracking page.'
                        : 'Use the secure tracking link from your confirmation email to view the latest patient-safe status.')
                    : 'Please continue the next operational step for this case.',
            ],
            team: $team,
            eyebrow: $isPatient ? 'Patient update' : 'Team update',
            preheader: sprintf('%s is now %s', $case->reference, $isPatient ? $this->to->patientLabel() : $this->to->label()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'case_id' => $this->case->id,
            'from' => $this->from->value,
            'to' => $this->to->value,
            'audience' => $this->audience,
        ];
    }
}
