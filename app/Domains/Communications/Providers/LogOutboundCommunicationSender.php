<?php

namespace App\Domains\Communications\Providers;

use App\Contracts\Communications\OutboundCommunicationSender;
use App\Enums\CommunicationChannel;
use App\Mail\CaseCommunicationMail;
use App\Models\CaseCommunication;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LogOutboundCommunicationSender implements OutboundCommunicationSender
{
    public function send(CaseCommunication $communication): array
    {
        $communication->loadMissing('case');

        $reference = sprintf('cf-mock-%s', Str::uuid());

        return match ($communication->channel) {
            CommunicationChannel::Email => $this->sendEmail($communication, $reference),
            CommunicationChannel::WhatsApp => $this->sendWhatsApp($communication, $reference),
            CommunicationChannel::PatientPortal => $this->logChannel($communication, $reference, 'patient_portal'),
            CommunicationChannel::Other => $this->logChannel($communication, $reference, 'other'),
            CommunicationChannel::Internal => throw new InvalidArgumentException('Internal comments are not outbound messages.'),
        };
    }

    /**
     * @return array{provider: string, reference: string, channel: string}
     */
    protected function sendEmail(CaseCommunication $communication, string $reference): array
    {
        $recipient = $communication->recipient;

        if (! is_string($recipient) || $recipient === '') {
            throw new InvalidArgumentException('Email communications require a recipient address.');
        }

        Mail::to($recipient)->send(new CaseCommunicationMail($communication));

        Log::info('CareFlow outbound email dispatched.', [
            'communication_uuid' => $communication->uuid,
            'recipient' => $recipient,
            'provider_reference' => $reference,
            'case_reference' => $communication->case?->reference,
        ]);

        return [
            'provider' => 'log_mail',
            'reference' => $reference,
            'channel' => CommunicationChannel::Email->value,
        ];
    }

    /**
     * @return array{provider: string, reference: string, channel: string}
     */
    protected function sendWhatsApp(CaseCommunication $communication, string $reference): array
    {
        $recipient = $communication->recipient;

        if (! is_string($recipient) || $recipient === '') {
            throw new InvalidArgumentException('WhatsApp communications require a recipient phone number.');
        }

        Log::info('CareFlow outbound WhatsApp message queued via mock provider.', [
            'communication_uuid' => $communication->uuid,
            'recipient' => $recipient,
            'provider_reference' => $reference,
            'body_preview' => Str::limit((string) $communication->body, 120),
            'case_reference' => $communication->case?->reference,
        ]);

        return [
            'provider' => 'mock_whatsapp',
            'reference' => $reference,
            'channel' => CommunicationChannel::WhatsApp->value,
        ];
    }

    /**
     * @return array{provider: string, reference: string, channel: string}
     */
    protected function logChannel(CaseCommunication $communication, string $reference, string $provider): array
    {
        Log::info('CareFlow outbound communication logged.', [
            'communication_uuid' => $communication->uuid,
            'channel' => $communication->channel->value,
            'provider_reference' => $reference,
            'case_reference' => $communication->case?->reference,
        ]);

        return [
            'provider' => $provider,
            'reference' => $reference,
            'channel' => $communication->channel->value,
        ];
    }
}
