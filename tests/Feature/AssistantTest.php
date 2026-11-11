<?php

use App\Ai\Agents\CareAssistantAgent;
use App\Ai\Tools\Assistant\AssignCase;
use App\Ai\Tools\Assistant\CreateTask;
use App\Ai\Tools\Assistant\GetCaseDetails;
use App\Ai\Tools\Assistant\SearchCases;
use App\Ai\Tools\Assistant\UpdateCaseStatus;
use App\Enums\CaseStatus;
use App\Enums\TeamRole;
use App\Models\AssistantThread;
use App\Models\CaseTask;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientEnquiry;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Tools\Request as ToolRequest;

function assistantTeam(TeamRole $role, bool $enabled = true): array
{
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $team->members()->attach($user, ['role' => $role->value]);
    $user->switchTeam($team);
    $team->ensureOrganizationSetting()->update([
        'assistant_enabled' => $enabled,
        'assistant_name' => 'Nora',
    ]);

    return [$team, $user];
}

function assistantMember(Team $team, TeamRole $role): User
{
    $user = User::factory()->create();
    $team->members()->attach($user, ['role' => $role->value]);

    return $user;
}

function assistantCase(Team $team, array $attributes = []): PatientCase
{
    $patient = Patient::factory()->create(['team_id' => $team->id]);
    $enquiry = PatientEnquiry::factory()->create(['team_id' => $team->id, 'patient_id' => $patient->id]);

    return PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
        ...$attributes,
    ]);
}

test('the command center explains the assistant is off until an admin turns it on', function () {
    [$team, $admin] = assistantTeam(TeamRole::Admin, enabled: false);

    $this->actingAs($admin)
        ->get("/{$team->slug}/assistant")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organizations/assistant')
            ->where('assistant.enabled', false)
            ->where('assistant.canManage', true));
});

test('people only see the actions their role allows', function () {
    [$team, $viewer] = assistantTeam(TeamRole::ManagementViewer);

    $this->actingAs($viewer)
        ->get("/{$team->slug}/assistant")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('assistant.canManage', false)
            ->where('assistant.knowledge', null)
            ->where('assistant.capabilities', fn ($tools) => collect($tools)->where('needs_approval', true)->isEmpty()));
});

test('a message is answered and remembered for that person in that hospital', function () {
    CareAssistantAgent::fake(['You have three open cases.']);
    [$team, $coordinator] = assistantTeam(TeamRole::Coordinator);

    $this->actingAs($coordinator)
        ->postJson("/{$team->slug}/assistant/messages", ['message' => 'How many open cases?'])
        ->assertOk()
        ->assertJsonPath('messages.0.role', 'user')
        ->assertJsonPath('messages.1.content', 'You have three open cases.');

    expect(AssistantThread::query()->where('team_id', $team->id)->where('user_id', $coordinator->id)->value('conversation_id'))
        ->not->toBeNull();
    CareAssistantAgent::assertPrompted('How many open cases?');
});

test('a conversation in one hospital never appears in another', function () {
    CareAssistantAgent::fake(['Answer for hospital A.']);
    [$teamA, $user] = assistantTeam(TeamRole::Coordinator);
    [$teamB] = assistantTeam(TeamRole::Admin);
    $teamB->members()->attach($user, ['role' => TeamRole::Coordinator->value]);

    $this->actingAs($user)->postJson("/{$teamA->slug}/assistant/messages", ['message' => 'Hello'])->assertOk();

    $this->actingAs($user)
        ->getJson("/{$teamB->slug}/assistant/state")
        ->assertOk()
        ->assertExactJson(['messages' => [], 'pending' => []]);
});

test('someone outside the hospital cannot use its assistant', function () {
    CareAssistantAgent::fake();
    [$team] = assistantTeam(TeamRole::Admin);
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->postJson("/{$team->slug}/assistant/messages", ['message' => 'Show me the cases'])
        ->assertForbidden();

    CareAssistantAgent::assertNeverPrompted();
});

test('messages are refused while the assistant is switched off', function () {
    CareAssistantAgent::fake();
    [$team, $coordinator] = assistantTeam(TeamRole::Coordinator, enabled: false);

    $this->actingAs($coordinator)
        ->postJson("/{$team->slug}/assistant/messages", ['message' => 'Hi'])
        ->assertForbidden()
        ->assertJsonPath('message', 'The assistant is switched off for this hospital.');

    CareAssistantAgent::assertNeverPrompted();
});

test('an empty message is rejected', function () {
    [$team, $coordinator] = assistantTeam(TeamRole::Coordinator);

    $this->actingAs($coordinator)
        ->postJson("/{$team->slug}/assistant/messages", ['message' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message' => 'The message field is required.']);
});

test('a provider failure gives the person a calm error instead of a crash', function () {
    CareAssistantAgent::fake(fn () => throw new RuntimeException('OpenAI timed out'));
    [$team, $coordinator] = assistantTeam(TeamRole::Coordinator);

    $this->actingAs($coordinator)
        ->postJson("/{$team->slug}/assistant/messages", ['message' => 'Hello'])
        ->assertStatus(502)
        ->assertJsonPath('message', 'The assistant could not answer just now. Please try again in a moment.');
});

test('a decision without a matching pending action does not call the AI', function () {
    CareAssistantAgent::fake();
    [$team, $coordinator] = assistantTeam(TeamRole::Coordinator);

    $this->actingAs($coordinator)
        ->postJson("/{$team->slug}/assistant/decisions", ['tool_call_id' => 'call_made_up', 'approve' => true])
        ->assertOk()
        ->assertExactJson(['messages' => [], 'pending' => []]);

    CareAssistantAgent::assertNeverPrompted();
});

test('clearing the chat starts a fresh conversation', function () {
    [$team, $coordinator] = assistantTeam(TeamRole::Coordinator);
    AssistantThread::factory()->create(['team_id' => $team->id, 'user_id' => $coordinator->id]);

    $this->actingAs($coordinator)
        ->deleteJson("/{$team->slug}/assistant/conversation")
        ->assertOk();

    expect(AssistantThread::query()->where('user_id', $coordinator->id)->value('conversation_id'))->toBeNull();
});

test('admins can name the assistant and give it hospital knowledge', function () {
    [$team, $admin] = assistantTeam(TeamRole::Admin, enabled: false);

    $this->actingAs($admin)
        ->patch("/{$team->slug}/assistant/settings", [
            'assistant_enabled' => true,
            'assistant_name' => 'Ada',
            'assistant_knowledge' => 'Airport pickup is free for surgery patients.',
        ])
        ->assertRedirect();

    $settings = $team->ensureOrganizationSetting()->refresh();
    expect($settings->assistant_enabled)->toBeTrue()
        ->and($settings->assistant_name)->toBe('Ada')
        ->and($settings->assistant_knowledge)->toBe('Airport pickup is free for surgery patients.');
});

test('coordinators cannot change the assistant settings', function () {
    [$team, $coordinator] = assistantTeam(TeamRole::Coordinator, enabled: false);

    $this->actingAs($coordinator)
        ->patch("/{$team->slug}/assistant/settings", [
            'assistant_enabled' => true,
            'assistant_name' => 'Ada',
        ])
        ->assertForbidden();

    expect($team->ensureOrganizationSetting()->refresh()->assistant_enabled)->toBeFalse();
});

test('the assistant knows the hospital knowledge but not other hospitals', function () {
    [$team, $coordinator] = assistantTeam(TeamRole::Coordinator);
    $team->ensureOrganizationSetting()->update(['assistant_knowledge' => 'Visiting hours end at 8pm.']);

    $instructions = (string) (new CareAssistantAgent($coordinator, $team))->instructions();

    expect($instructions)->toContain('Visiting hours end at 8pm.')
        ->toContain($team->name)
        ->toContain($coordinator->name);
});

test('tools are offered only when the person has the matching permission', function () {
    [$team, $nurse] = assistantTeam(TeamRole::Nurse);
    $coordinator = assistantMember($team, TeamRole::Coordinator);

    expect(CareAssistantAgent::toolClassesFor($nurse, $team))->not->toContain(AssignCase::class)
        ->and(CareAssistantAgent::toolClassesFor($coordinator, $team))->toContain(AssignCase::class, UpdateCaseStatus::class, CreateTask::class);
});

test('a doctor searching through the assistant only finds their own cases', function () {
    [$team, $doctor] = assistantTeam(TeamRole::Doctor);
    $mine = assistantCase($team, ['assigned_doctor_id' => $doctor->id]);
    $notMine = assistantCase($team);

    $result = json_decode((string) (new SearchCases($doctor, $team))->handle(new ToolRequest([])), true);

    expect(collect($result['cases'])->pluck('reference')->all())->toBe([$mine->reference])
        ->not->toContain($notMine->reference);
});

test('a case from another hospital cannot be read through the assistant', function () {
    [$team, $coordinator] = assistantTeam(TeamRole::Coordinator);
    [$otherTeam] = assistantTeam(TeamRole::Admin);
    $foreign = assistantCase($otherTeam);

    $result = json_decode((string) (new GetCaseDetails($coordinator, $team))->handle(new ToolRequest(['reference' => $foreign->reference])), true);

    expect($result)->toHaveKey('error')->not->toHaveKey('patient');
});

test('assigning a case asks for approval in plain words, then assigns once run', function () {
    [$team, $coordinator] = assistantTeam(TeamRole::Coordinator);
    $doctor = assistantMember($team, TeamRole::Doctor);
    $case = assistantCase($team);
    $tool = new AssignCase($coordinator, $team);
    $request = new ToolRequest(['reference' => $case->reference, 'doctor_user_id' => $doctor->id]);

    expect($tool->shouldRequestApproval($request)?->reason)
        ->toBe("Assign {$doctor->name} as doctor on case {$case->reference}.");

    $tool->handle($request);

    expect($case->refresh()->assigned_doctor_id)->toBe($doctor->id);
});

test('an assignment to someone outside the hospital is refused', function () {
    [$team, $coordinator] = assistantTeam(TeamRole::Coordinator);
    $case = assistantCase($team);
    $stranger = User::factory()->create();

    $result = json_decode((string) (new AssignCase($coordinator, $team))->handle(
        new ToolRequest(['reference' => $case->reference, 'doctor_user_id' => $stranger->id]),
    ), true);

    expect($result)->toHaveKey('error')
        ->and($case->refresh()->assigned_doctor_id)->toBeNull();
});

test('a status change the workflow does not allow is reported and nothing changes', function () {
    [$team, $coordinator] = assistantTeam(TeamRole::Coordinator);
    $case = assistantCase($team, ['status' => CaseStatus::NewEnquiry]);

    $result = json_decode((string) (new UpdateCaseStatus($coordinator, $team))->handle(
        new ToolRequest(['reference' => $case->reference, 'status' => CaseStatus::TreatmentCompleted->value]),
    ), true);

    expect($result)->toHaveKey('error')
        ->and($case->refresh()->status)->toBe(CaseStatus::NewEnquiry);
});

test('creating a task records it for the chosen person with its due date', function () {
    $this->freezeTime();
    [$team, $coordinator] = assistantTeam(TeamRole::Coordinator);
    $colleague = assistantMember($team, TeamRole::Coordinator);
    $case = assistantCase($team);
    $tool = new CreateTask($coordinator, $team);
    $request = new ToolRequest([
        'reference' => $case->reference,
        'title' => 'Call the patient',
        'due_date' => now()->addDay()->toDateString(),
        'assigned_user_id' => $colleague->id,
    ]);

    expect($tool->shouldRequestApproval($request)?->reason)
        ->toBe("Create the task \"Call the patient\" on case {$case->reference} for {$colleague->name}, due ".now()->addDay()->toFormattedDateString().'.');

    $tool->handle($request);

    $task = CaseTask::query()->where('patient_case_id', $case->id)->sole();
    expect($task->title)->toBe('Call the patient')
        ->and($task->assigned_user_id)->toBe($colleague->id)
        ->and($task->due_at->toDateString())->toBe(now()->addDay()->toDateString());
});

test('an admin can switch AI document processing and the assistant on from their switches', function () {
    [$team, $admin] = assistantTeam(TeamRole::Admin, enabled: false);

    $this->actingAs($admin)
        ->patch("/{$team->slug}/organization/ai", ['ai_processing_enabled' => true])
        ->assertRedirect();

    $this->actingAs($admin)
        ->patch("/{$team->slug}/organization/ai", ['assistant_enabled' => true])
        ->assertRedirect();

    $settings = $team->ensureOrganizationSetting()->refresh();
    expect($settings->ai_processing_enabled)->toBeTrue()
        ->and($settings->assistant_enabled)->toBeTrue();
});

test('coordinators cannot switch AI features', function () {
    [$team, $coordinator] = assistantTeam(TeamRole::Coordinator, enabled: false);

    $this->actingAs($coordinator)
        ->patch("/{$team->slug}/organization/ai", ['assistant_enabled' => true])
        ->assertForbidden();

    expect($team->ensureOrganizationSetting()->refresh()->assistant_enabled)->toBeFalse();
});

test('switching AI features needs at least one switch value', function () {
    [$team, $admin] = assistantTeam(TeamRole::Admin, enabled: false);

    $this->actingAs($admin)
        ->patch("/{$team->slug}/organization/ai", [])
        ->assertSessionHasErrors(['ai_processing_enabled', 'assistant_enabled']);
});
