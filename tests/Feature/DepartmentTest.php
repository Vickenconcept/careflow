<?php

use App\Enums\TeamRole;
use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('team members can view departments', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    Department::factory()->create([
        'team_id' => $team->id,
        'name' => 'Cardiology',
        'slug' => 'cardiology',
        'sort_order' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('organizations.departments.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organizations/departments/index')
            ->has('departments', 1)
            ->where('departments.0.name', 'Cardiology')
            ->missing('next_sort_order'));
});

test('owners can create departments', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->post(route('organizations.departments.store', $team), [
            'name' => 'Orthopedics',
            'code' => 'ORTHO',
            'description' => 'Bone and joint care',
            'is_active' => true,
        ])
        ->assertRedirect(route('organizations.departments.index', $team));

    $this->assertDatabaseHas('departments', [
        'team_id' => $team->id,
        'name' => 'Orthopedics',
        'slug' => 'orthopedics',
        'code' => 'ORTHO',
        'sort_order' => 1,
    ]);
});

test('department code is auto-generated when omitted', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->post(route('organizations.departments.store', $team), [
            'name' => 'Plastic Surgery',
            'is_active' => true,
        ])
        ->assertRedirect(route('organizations.departments.index', $team));

    $this->assertDatabaseHas('departments', [
        'team_id' => $team->id,
        'name' => 'Plastic Surgery',
        'code' => 'PS',
    ]);
});

test('owners can reorder departments up and down', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $first = Department::factory()->create([
        'team_id' => $team->id,
        'name' => 'Cardiology',
        'slug' => 'cardiology',
        'sort_order' => 0,
    ]);
    $second = Department::factory()->create([
        'team_id' => $team->id,
        'name' => 'Neurology',
        'slug' => 'neurology',
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->patch(route('organizations.departments.reorder', [
            'current_team' => $team,
            'department' => $second,
        ]), [
            'direction' => 'up',
        ])
        ->assertRedirect();

    expect($first->fresh()->sort_order)->toBe(1)
        ->and($second->fresh()->sort_order)->toBe(0);

    $this->actingAs($user)
        ->patch(route('organizations.departments.reorder', [
            'current_team' => $team,
            'department' => $second->fresh(),
        ]), [
            'direction' => 'down',
        ])
        ->assertRedirect();

    expect($first->fresh()->sort_order)->toBe(0)
        ->and($second->fresh()->sort_order)->toBe(1);
});

test('coordinators cannot create departments', function () {
    $owner = User::factory()->create();
    $coordinator = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $coordinator->switchTeam($team);

    $this->actingAs($coordinator)
        ->post(route('organizations.departments.store', $team), [
            'name' => 'Neurology',
        ])
        ->assertForbidden();
});

test('departments are isolated between teams', function () {
    $user = User::factory()->create();
    $ownTeam = $user->currentTeam;
    $otherTeam = Team::factory()->create();

    $foreignDepartment = Department::factory()->create([
        'team_id' => $otherTeam->id,
        'name' => 'Secret Department',
        'slug' => 'secret-department',
    ]);

    $this->actingAs($user)
        ->get(route('organizations.departments.edit', [
            'current_team' => $ownTeam,
            'department' => $foreignDepartment,
        ]))
        ->assertNotFound();
});

test('owners can update and delete departments', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $department = Department::factory()->create([
        'team_id' => $team->id,
        'name' => 'IVF',
        'slug' => 'ivf',
        'sort_order' => 0,
    ]);

    $this->actingAs($user)
        ->patch(route('organizations.departments.update', [
            'current_team' => $team,
            'department' => $department,
        ]), [
            'name' => 'IVF & Fertility',
            'description' => 'Fertility services',
            'is_active' => true,
        ])
        ->assertRedirect(route('organizations.departments.index', $team));

    expect($department->fresh()->name)->toBe('IVF & Fertility')
        ->and($department->fresh()->slug)->toBe('ivf-fertility')
        ->and($department->fresh()->sort_order)->toBe(0);

    $this->actingAs($user)
        ->delete(route('organizations.departments.destroy', [
            'current_team' => $team,
            'department' => $department->fresh(),
        ]))
        ->assertRedirect(route('organizations.departments.index', $team));

    expect($department->fresh()->trashed())->toBeTrue();
});
