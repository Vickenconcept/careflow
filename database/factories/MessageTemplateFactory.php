<?php

namespace Database\Factories;

use App\Enums\CommunicationChannel;
use App\Enums\MessageTemplateKey;
use App\Models\MessageTemplate;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MessageTemplate>
 */
class MessageTemplateFactory extends Factory
{
    protected $model = MessageTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'uuid' => (string) Str::uuid(),
            'key' => MessageTemplateKey::EnquiryReceived,
            'name' => 'Enquiry received',
            'channel' => CommunicationChannel::Email,
            'is_active' => true,
            'subject_translations' => ['en' => 'We received your enquiry'],
            'body_translations' => ['en' => 'Dear {{patient_name}}, case {{case_reference}} was received.'],
            'notes' => null,
        ];
    }
}
