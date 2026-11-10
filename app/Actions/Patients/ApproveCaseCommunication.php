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

            if (! in_array($locked->status, [CommunicationStatus::Draft, CommunicationStatus::AwaitingApproval], true)) {
                throw new InvalidArgumentException('Only draft/awaiting-approval communications can be approved.');
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
                    ? sprintf(
                        'Communication approved and sent via %s',
                        $providerPayload['provider'] ?? 'provider',
                    )
                    : 'Communication approved for send',
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
