<?php

namespace App\Actions\Patients;

use App\Enums\CaseActivityType;
use App\Enums\CommunicationStatus;
use App\Models\CaseCommunication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReviseCaseCommunicationDraft
{
    public function __construct(private RecordCaseActivity $recordActivity) {}

    /**
     * Edit the wording of a message that has not been sent yet.
     */
    public function update(CaseCommunication $communication, User $actor, string $body, ?string $subject): CaseCommunication
    {
        $this->ensureEditable($communication);

        $communication->forceFill([
            'body' => trim($body),
            'subject' => $subject,
        ])->save();

        return $communication;
    }

    /**
     * Throw away a draft so it never reaches the patient.
     */
    public function discard(CaseCommunication $communication, User $actor): void
    {
        $this->ensureEditable($communication);

        DB::transaction(function () use ($communication, $actor): void {
            $case = $communication->case;
            $communication->delete();

            $this->recordActivity->handle(
                case: $case,
                type: CaseActivityType::CommunicationLogged,
                summary: 'Message draft discarded',
                actor: $actor,
                properties: ['communication_uuid' => $communication->uuid],
            );
        });
    }

    protected function ensureEditable(CaseCommunication $communication): void
    {
        if (! in_array($communication->status, [CommunicationStatus::Draft, CommunicationStatus::AwaitingApproval], true)) {
            throw new InvalidArgumentException('Only messages that have not been sent can be changed.');
        }
    }
}
