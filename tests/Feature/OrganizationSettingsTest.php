<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('organization settings page can be rendered for team members', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->ensureOrganizationSetting();

    $this->actingAs($user)
        ->get(route('organizations.settings.edit', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organizations/settings')
            ->has('settings')
            ->where('permissions.canManageOrganizationSettings', true));
});

test('owners can update organization settings', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->ensureOrganizationSetting();

    $this->actingAs($user)
        ->patch(route('organizations.settings.update', $team), [
            'legal_name' => 'CareFlow International Medical Centre',
            'contact_email' => 'ops@careflow-demo.test',
            'contact_phone' => '+90 392 000 0000',
            'address_line_1' => '1 Demo Street',
            'address_line_2' => null,
            'city' => 'Nicosia',
            'state' => null,
            'postal_code' => '99010',
            'country' => 'CY',
            'timezone' => 'Asia/Nicosia',
            'default_currency' => 'EUR',
            'default_language' => 'en',
            'supported_languages' => ['en', 'tr', 'ar'],
            'brand_primary_color' => '#0F766E',
            'brand_secondary_color' => '#134E4A',
            'ai_processing_enabled' => true,
            'ai_provider' => 'openai',
        ])
        ->assertRedirect(route('organizations.settings.edit', $team));

    $settings = $team->fresh()->organizationSetting;

    expect($settings->legal_name)->toBe('CareFlow International Medical Centre')
        ->and($settings->ai_provider)->toBe('openai')
        ->and($settings->ai_processing_enabled)->toBeTrue()
        ->and($settings->supported_languages)->toBe(['en', 'tr', 'ar']);
});

test('coordinators cannot update organization settings', function () {
    $owner = User::factory()->create();
    $coordinator = User::factory()->create();
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $coordinator->switchTeam($team);

    $this->actingAs($coordinator)
        ->patch(route('organizations.settings.update', $team), [
            'legal_name' => 'Unauthorized Update',
            'timezone' => 'UTC',
            'default_currency' => 'EUR',
            'default_language' => 'en',
            'supported_languages' => ['en'],
            'ai_processing_enabled' => false,
            'ai_provider' => 'openai',
        ])
        ->assertForbidden();
});

test('platform admin flag alone does not grant organization membership', function () {
    $platformAdmin = User::factory()->create([
        'is_platform_admin' => true,
    ]);
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting();

    expect($platformAdmin->isPlatformAdmin())->toBeTrue()
        ->and($platformAdmin->belongsToTeam($team))->toBeFalse();

    $this->actingAs($platformAdmin)
        ->get(route('organizations.settings.edit', $team))
        ->assertForbidden();
});
