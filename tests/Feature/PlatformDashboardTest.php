<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Support\Impersonation;
use Inertia\Testing\AssertableInertia as Assert;

test('users listed in ADMIN_EMAILS are treated as platform admins', function () {
    config()->set('careflow.admin_emails', ['ops@careflow.test']);

    $admin = User::factory()->create([
        'email' => 'ops@careflow.test',
        'is_platform_admin' => false,
    ]);

    expect($admin->isPlatformAdmin())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/dashboard')
            ->where('admin_emails.0', 'ops@careflow.test')
            ->has('users')
            ->has('organizations'));
});

test('platform admins can view the platform dashboard with the database flag', function () {
    $admin = User::factory()->create([
        'is_platform_admin' => true,
    ]);

    Team::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/dashboard')
            ->where('stats.organizations', fn ($count) => $count >= 2)
            ->has('organizations')
            ->has('disclaimer'));
});

test('non platform admins cannot view the platform dashboard', function () {
    $user = User::factory()->create([
        'is_platform_admin' => false,
        'email' => 'regular@example.test',
    ]);

    config()->set('careflow.admin_emails', []);

    $this->actingAs($user)
        ->get(route('platform.dashboard'))
        ->assertForbidden();
});

test('platform admins can impersonate organization users and leave impersonation', function () {
    config()->set('careflow.admin_emails', ['ops@careflow.test']);

    $admin = User::factory()->create([
        'email' => 'ops@careflow.test',
        'is_platform_admin' => false,
    ]);

    $doctor = User::factory()->create([
        'email' => 'doctor@example.test',
        'is_platform_admin' => false,
    ]);
    $team = Team::factory()->create(['slug' => 'careflow-impersonation']);
    $team->members()->attach($doctor, ['role' => TeamRole::Doctor->value]);
    $doctor->switchTeam($team);

    $this->actingAs($admin)
        ->post(route('platform.impersonation.start', ['user' => $doctor]))
        ->assertRedirect(route('dashboard', ['current_team' => $team->slug]));

    $this->assertAuthenticatedAs($doctor);
    expect(session(Impersonation::SESSION_KEY))->toBe($admin->id);

    $this->delete(route('platform.impersonation.stop'))
        ->assertRedirect(route('platform.dashboard'));

    $this->assertAuthenticatedAs($admin);
    expect(session()->has(Impersonation::SESSION_KEY))->toBeFalse();
});

test('platform admins cannot impersonate other platform admins', function () {
    $admin = User::factory()->create([
        'is_platform_admin' => true,
    ]);
    $otherAdmin = User::factory()->create([
        'is_platform_admin' => true,
    ]);

    $this->actingAs($admin)
        ->from(route('platform.dashboard'))
        ->post(route('platform.impersonation.start', ['user' => $otherAdmin]))
        ->assertRedirect()
        ->assertSessionHasErrors('impersonation');
});
