<?php

namespace App\Actions\Patients;

use App\Actions\Organizations\EnsureDefaultMessageTemplates;
use App\Domains\Communications\MessageTemplateRenderer;
use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Enums\MessageTemplateKey;
use App\Models\CaseCommunication;
use App\Models\MessageTemplate;
use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tell the patient about a step in their case, using the hospital's message template.
 *
 * Every notice is added to the case conversation, so staff can always see what the
 * patient was told. A notice is either sent straight away (when the staff action was
 * itself "tell the patient", like sending a proposal) or left as a draft for a
 * coordinator to approve. Delivery problems never undo the staff action: the message
 * is kept as a draft to send again.
 */
class NotifyPatient
{
    /**
     * The notice created during this request, so the controller can tell staff what happened.
     */
    public ?CaseCommunication $lastNotice = null;

    public function __construct(
        private EnsureDefaultMessageTemplates $ensureTemplates,
        private MessageTemplateRenderer $renderer,
        private CreateCaseCommunication $createCommunication,
        private ApproveCaseCommunication $approveCommunication,
    ) {}

    /**
     * @param  string|null  $details  Extra lines added under the template text, such as appointment details.
     */
    public function handle(
        PatientCase $case,
        MessageTemplateKey $key,
        User $actor,
        bool $sendNow,
        ?string $details = null,
    ): ?CaseCommunication {
        $case->loadMissing(['team.organizationSetting', 'patient']);

        if (! $case->team->moduleEnabled('communications') || $case->patient === null) {
            return null;
        }

        $this->ensureTemplates->handle($case->team);

        $template = MessageTemplate::query()
            ->where('team_id', $case->team_id)
            ->where('key', $key->value)
            ->where('is_active', true)
            ->first();

        if ($template === null) {
            return null;
        }

        $rendered = $this->renderer->render($template, $case->patient->preferred_language ?: 'en', [
            'patient_name' => $case->patient->fullName(),
            'case_reference' => $case->reference,
            'organization_name' => $case->team->organizationSetting?->legal_name ?: $case->team->name,
        ]);

        $body = $rendered['body'];

        if (filled($details)) {
            $body = $this->renderer->withDetails($body, (string) $details);
        }

        $channel = filled($case->patient->email) ? CommunicationChannel::Email : CommunicationChannel::PatientPortal;

        $communication = $this->createCommunication->handle($case, $actor, [
            'channel' => $channel->value,
            'subject' => $rendered['subject'],
            'body' => $body,
            'language' => $rendered['language'],
            'recipient' => $case->patient->email,
        ]);

        if ($sendNow && $communication->status === CommunicationStatus::AwaitingApproval) {
            try {
                $communication = $this->approveCommunication->handle($communication, $actor, markSent: true);
            } catch (Throwable $exception) {
                Log::warning('Patient notice could not be delivered; kept as a draft', [
                    'communication_uuid' => $communication->uuid,
                    'template' => $key->value,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);

                $communication = $communication->fresh();
            }
        }

        return $this->lastNotice = $communication;
    }

    /**
     * One sentence for the staff toast about what the patient was told, if anything.
     */
    public function staffSummary(): ?string
    {
        $notice = $this->lastNotice;

        return match (true) {
            $notice === null => null,
            $notice->status === CommunicationStatus::Sent && $notice->channel === CommunicationChannel::Email => __('The patient was emailed.'),
            $notice->status === CommunicationStatus::Sent => __('The patient can see it on their case page.'),
            default => __('A message for the patient is waiting for your approval in Communications.'),
        };
    }
}
