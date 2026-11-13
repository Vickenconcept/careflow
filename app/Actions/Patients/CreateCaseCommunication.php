<?php

namespace App\Actions\Patients;

use App\Ai\Agents\AdministrativeReplyDraftAgent;
use App\Enums\CaseActivityType;
use App\Enums\CommunicationChannel;
use App\Enums\CommunicationDirection;
use App\Enums\CommunicationStatus;
use App\Models\CaseCommunication;
use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Ai\Enums\Lab;

class CreateCaseCommunication
{
    public function __construct(
        private RecordCaseActivity $recordActivity,
    ) {}

    /**
     * @param  array{
     *     channel: string,
     *     direction?: string,
     *     language?: string,
     *     subject?: string|null,
     *     body?: string|null,
     *     recipient?: string|null,
     *     draft_with_ai?: bool,
     *     ai_brief?: string|null,
     *     log_only?: bool
     * }  $data
     */
    public function handle(PatientCase $case, User $actor, array $data): CaseCommunication
    {
        return DB::transaction(function () use ($case, $actor, $data) {
            /** @var PatientCase $locked */
            $locked = PatientCase::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();

            $channel = CommunicationChannel::from($data['channel']);
            // No WhatsApp sender is connected: WhatsApp entries record a conversation that
            // happened outside CareFlow and are never queued as if CareFlow would send them.
            $logOnly = (bool) ($data['log_only'] ?? false) || in_array($channel, [CommunicationChannel::WhatsApp, CommunicationChannel::Other], true);
            $draftWithAi = (bool) ($data['draft_with_ai'] ?? false);

            $body = trim((string) ($data['body'] ?? ''));
            $subject = $data['subject'] ?? null;
            $aiMetadata = null;
            $isAiDraft = false;

            if ($draftWithAi) {
                if ((string) config('careflow.ai.provider', 'openai') !== 'openai') {
                    throw new InvalidArgumentException('AI drafting is not set up yet. Please contact your CareFlow administrator.');
                }

                $brief = trim((string) ($data['ai_brief'] ?? ''));
                if ($brief === '') {
                    throw new InvalidArgumentException('Provide a brief for the AI administrative draft.');
                }

                $response = (new AdministrativeReplyDraftAgent)->prompt(
                    prompt: implode("\n\n", [
                        'Patient preferred language: '.($locked->patient?->preferred_language ?? 'en'),
                        'Case reference: '.$locked->reference,
                        'Channel: '.$channel->value,
                        'Staff brief:',
                        $brief,
                    ]),
                    provider: Lab::OpenAI,
                );

                if ((bool) ($response['contains_clinical_advice'] ?? false)) {
                    throw new InvalidArgumentException('AI refused to draft clinical advice. A clinician must respond to clinical questions.');
                }

                $body = trim((string) ($response['body'] ?? ''));
                $subject = $subject ?: ($response['subject'] ?? null);
                $isAiDraft = true;
                $aiMetadata = [
                    'provider' => 'openai',
                    'agent' => AdministrativeReplyDraftAgent::class,
                    'warnings' => $response['warnings'] ?? [],
                ];
            }

            if ($body === '') {
                throw new InvalidArgumentException('Message body is required.');
            }

            $direction = isset($data['direction'])
                ? CommunicationDirection::from($data['direction'])
                : ($channel === CommunicationChannel::Internal
                    ? CommunicationDirection::Internal
                    : CommunicationDirection::Outbound);

            if ($channel === CommunicationChannel::Internal || $logOnly) {
                $status = CommunicationStatus::Logged;
                $requiresApproval = false;
            } else {
                $status = CommunicationStatus::AwaitingApproval;
                $requiresApproval = true;
            }

            $communication = CaseCommunication::query()->create([
                'team_id' => $locked->team_id,
                'patient_case_id' => $locked->id,
                'created_by_user_id' => $actor->id,
                'uuid' => (string) Str::uuid(),
                'channel' => $channel,
                'direction' => $direction,
                'status' => $status,
                'language' => $data['language'] ?? $locked->patient?->preferred_language ?? 'en',
                'subject' => $subject,
                'body' => $body,
                'sender' => $direction === CommunicationDirection::Inbound
                    ? ($locked->patient?->fullName() ?? 'Patient')
                    : $actor->email,
                'recipient' => $data['recipient'] ?? $locked->patient?->email,
                'is_ai_draft' => $isAiDraft,
                'requires_approval' => $requiresApproval,
                'ai_metadata' => $aiMetadata,
                'is_demo' => $locked->is_demo,
            ]);

            $locked->forceFill(['last_activity_at' => now()])->save();

            $this->recordActivity->handle(
                case: $locked,
                type: CaseActivityType::CommunicationLogged,
                summary: $isAiDraft
                    ? 'AI administrative draft created (awaiting approval)'
                    : sprintf('Communication logged (%s)', $channel->label()),
                actor: $actor,
                properties: [
                    'communication_uuid' => $communication->uuid,
                    'channel' => $channel->value,
                    'status' => $status->value,
                    'is_ai_draft' => $isAiDraft,
                ],
            );

            return $communication->fresh(['createdBy:id,name']);
        });
    }
}
