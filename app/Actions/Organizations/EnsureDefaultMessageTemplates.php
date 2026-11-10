<?php

namespace App\Actions\Organizations;

use App\Enums\CommunicationChannel;
use App\Enums\MessageTemplateKey;
use App\Models\MessageTemplate;
use App\Models\Team;
use Illuminate\Support\Str;

class EnsureDefaultMessageTemplates
{
    public function handle(Team $team): void
    {
        foreach (MessageTemplateKey::cases() as $key) {
            MessageTemplate::query()->firstOrCreate(
                [
                    'team_id' => $team->id,
                    'key' => $key->value,
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $key->label(),
                    'channel' => CommunicationChannel::Email,
                    'is_active' => true,
                    'subject_translations' => [
                        'en' => $this->defaultSubject($key),
                    ],
                    'body_translations' => [
                        'en' => $this->defaultBody($key),
                    ],
                    'notes' => 'Default organization template. Customize translations as needed. Placeholders: {{patient_name}}, {{case_reference}}, {{organization_name}}.',
                ],
            );
        }
    }

    protected function defaultSubject(MessageTemplateKey $key): string
    {
        return match ($key) {
            MessageTemplateKey::EnquiryReceived => 'We received your enquiry — {{case_reference}}',
            MessageTemplateKey::AdditionalDocumentsRequired => 'Additional documents needed — {{case_reference}}',
            MessageTemplateKey::DocumentsReceived => 'Documents received — {{case_reference}}',
            MessageTemplateKey::CaseAssignedForReview => 'Your case is under clinical review — {{case_reference}}',
            MessageTemplateKey::EvaluationCompleted => 'Clinical evaluation update — {{case_reference}}',
            MessageTemplateKey::TreatmentProposalAvailable => 'Treatment proposal available — {{case_reference}}',
            MessageTemplateKey::AppointmentConfirmation => 'Appointment confirmation — {{case_reference}}',
            MessageTemplateKey::TravelInformation => 'Travel coordination information — {{case_reference}}',
            MessageTemplateKey::FollowUpReminder => 'Follow-up reminder — {{case_reference}}',
        };
    }

    protected function defaultBody(MessageTemplateKey $key): string
    {
        $greeting = "Dear {{patient_name}},\n\n";
        $signoff = "\n\nKind regards,\n{{organization_name}} International Patient Services";

        return match ($key) {
            MessageTemplateKey::EnquiryReceived => $greeting.'Thank you for contacting us. We have created case {{case_reference}} and our coordination team will review your enquiry shortly.'.$signoff,
            MessageTemplateKey::AdditionalDocumentsRequired => $greeting.'To continue reviewing case {{case_reference}}, please upload the additional documents requested by our clinical team.'.$signoff,
            MessageTemplateKey::DocumentsReceived => $greeting.'We have received your documents for case {{case_reference}}. Our team will review them and contact you with next steps.'.$signoff,
            MessageTemplateKey::CaseAssignedForReview => $greeting.'Case {{case_reference}} has been assigned for clinical review. We will update you once the evaluation is complete.'.$signoff,
            MessageTemplateKey::EvaluationCompleted => $greeting.'The clinical evaluation for case {{case_reference}} has progressed. Your coordinator will share the next administrative steps.'.$signoff,
            MessageTemplateKey::TreatmentProposalAvailable => $greeting.'A treatment proposal is available for case {{case_reference}}. Please review it and reply with your decision or questions.'.$signoff,
            MessageTemplateKey::AppointmentConfirmation => $greeting.'Your appointment details for case {{case_reference}} have been confirmed by the hospital scheduling team. Your coordinator will share timing and location instructions.'.$signoff,
            MessageTemplateKey::TravelInformation => $greeting.'Please find travel coordination guidance for case {{case_reference}}. Contact your coordinator if arrival or accommodation details change.'.$signoff,
            MessageTemplateKey::FollowUpReminder => $greeting.'This is a follow-up reminder for case {{case_reference}}. Please contact your coordinator if you need support.'.$signoff,
        };
    }
}
