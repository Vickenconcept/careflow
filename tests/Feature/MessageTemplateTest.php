<?php

use App\Enums\MessageTemplateKey;
use App\Enums\TeamRole;
use App\Models\MessageTemplate;
use App\Models\Team;
use App\Models\User;

test('coordinators can view and update organization message templates', function () {
    $owner = User::factory()->create();
    $coordinator = User::factory()->create();
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $coordinator->switchTeam($team);

    $this->actingAs($coordinator)
        ->get(route('organizations.message-templates.index', [
            'current_team' => $team,
        ]))
        ->assertOk();

    expect(MessageTemplate::query()->where('team_id', $team->id)->count())
        ->toBe(count(MessageTemplateKey::cases()));

    $this->actingAs($coordinator)
        ->put(route('organizations.message-templates.upsert', [
            'current_team' => $team,
        ]), [
            'key' => MessageTemplateKey::EnquiryReceived->value,
            'name' => 'Enquiry confirmation',
            'channel' => 'email',
            'is_active' => true,
            'subject_translations' => [
                'en' => 'Thanks {{patient_name}}',
            ],
            'body_translations' => [
                'en' => 'Case {{case_reference}} received by {{organization_name}}.',
            ],
        ])
        ->assertRedirect();

    $template = MessageTemplate::query()
        ->where('team_id', $team->id)
        ->where('key', MessageTemplateKey::EnquiryReceived->value)
        ->firstOrFail();

    expect($template->name)->toBe('Enquiry confirmation')
        ->and($template->body_translations['en'])->toContain('{{case_reference}}');
});
