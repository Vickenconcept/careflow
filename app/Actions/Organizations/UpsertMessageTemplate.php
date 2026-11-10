<?php

namespace App\Actions\Organizations;

use App\Enums\CommunicationChannel;
use App\Enums\MessageTemplateKey;
use App\Models\MessageTemplate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Str;

class UpsertMessageTemplate
{
    /**
     * @param  array{
     *     key: string,
     *     name: string,
     *     channel: string,
     *     is_active?: bool,
     *     subject_translations?: array<string, string>|null,
     *     body_translations: array<string, string>,
     *     notes?: string|null
     * }  $data
     */
    public function handle(Team $team, User $actor, array $data): MessageTemplate
    {
        $key = MessageTemplateKey::from($data['key']);

        $template = MessageTemplate::query()
            ->where('team_id', $team->id)
            ->where('key', $key->value)
            ->first();

        $payload = [
            'name' => $data['name'],
            'channel' => CommunicationChannel::from($data['channel']),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'subject_translations' => $data['subject_translations'] ?? [],
            'body_translations' => $data['body_translations'],
            'notes' => $data['notes'] ?? null,
        ];

        if ($template === null) {
            return MessageTemplate::query()->create([
                'team_id' => $team->id,
                'uuid' => (string) Str::uuid(),
                'key' => $key,
                ...$payload,
            ]);
        }

        $template->fill($payload)->save();

        return $template->fresh();
    }
}
