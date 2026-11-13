<?php

namespace App\Actions\Patients;

use App\Contracts\Communications\OutboundCommunicationSender;
use App\Enums\CaseActivityType;
use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Models\CaseCommunication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApproveCaseCommunication
{
    public function __construct(
        private RecordCaseActivity $recordActivity,
        private OutboundCommunicationSender $outboundSender,
    ) {}

    /**
     * Approve an outbound draft. When markSent is true, dispatch via the configured sender.
     */
    public function handle(CaseCommunication $communication, User $actor, bool $markSent = false): CaseCommunication
    {
        return DB::transaction(function () use ($communication, $actor, $markSent) {
            /** @var CaseCommunication $locked */
            $locked = CaseCommunication::query()->whereKey($communication->id)->lockForUpdate()->firstOrFail();

            $sendable = [CommunicationStatus::Draft, CommunicationStatus::AwaitingApproval];

            // Messages approved earlier without being sent can still be sent later.
            if ($markSent) {
                $sendable[] = CommunicationStatus::Approved;
            }

            if (! in_array($locked->status, $sendable, true)) {
                throw new InvalidArgumentException('This message has already been handled.');
            }

            if ($locked->ai_metadata && ($locked->ai_metadata['contains_clinical_advice'] ?? false)) {
                throw new InvalidArgumentException('Communications containing clinical advice cannot be approved through this flow.');
            }

            $providerPayload = null;

            if ($markSent) {
                if ($locked->channel === CommunicationChannel::Internal) {
                    throw new InvalidArgumentException('Internal comments cannot be marked as sent.');
                }

                $providerPayload = $this->outboundSender->send($locked);
            }

            $locked->forceFill([
                'status' => $markSent ? CommunicationStatus::Sent : CommunicationStatus::Approved,
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'sent_at' => $markSent ? now() : null,
                'provider_reference' => $providerPayload['reference'] ?? $locked->provider_reference,
                'requires_approval' => false,
            ])->save();

            $case = $locked->case()->lockForUpdate()->firstOrFail();
            $case->forceFill(['last_activity_at' => now()])->save();

            $this->recordActivity->handle(
                case: $case,
                type: CaseActivityType::CommunicationApproved,
                summary: $markSent
                    ? match ($locked->channel) {
                        CommunicationChannel::Email => 'Message sent to the patient by email',
                        CommunicationChannel::PatientPortal => 'Message shared with the patient in the portal',
                        default => 'Message approved and sent',
                    }
                : 'Message approved',
                actor: $actor,
                properties: [
                    'communication_uuid' => $locked->uuid,
                    'status' => $locked->status->value,
                    'provider' => $providerPayload['provider'] ?? null,
                    'provider_reference' => $providerPayload['reference'] ?? null,
                ],
            );

            return $locked->fresh(['createdBy:id,name', 'approvedBy:id,name']);
        });
    }
}
