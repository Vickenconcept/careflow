<?php

use App\Enums\TeamPermission;
use App\Enums\TeamRole;

test('careflow roles expose the expected permission matrix', function (TeamRole $role, array $expected) {
    foreach ($expected as $permissionValue => $allowed) {
        $permission = TeamPermission::from($permissionValue);

        expect($role->hasPermission($permission))
            ->toBe($allowed, "{$role->value} should ".($allowed ? 'have' : 'not have')." {$permission->value}");
    }
})->with([
    'owner has clinical and admin permissions' => [
        TeamRole::Owner,
        [
            TeamPermission::ManageOrganizationSettings->value => true,
            TeamPermission::ManageDepartments->value => true,
            TeamPermission::ApproveClinical->value => true,
            TeamPermission::ViewReports->value => true,
        ],
    ],
    'admin manages organization but cannot approve clinical content' => [
        TeamRole::Admin,
        [
            TeamPermission::ManageOrganizationSettings->value => true,
            TeamPermission::ManageDepartments->value => true,
            TeamPermission::ManageCases->value => true,
            TeamPermission::ApproveClinical->value => false,
            TeamPermission::DeleteTeam->value => false,
        ],
    ],
    'coordinator manages cases but not organization settings' => [
        TeamRole::Coordinator,
        [
            TeamPermission::ManageCases->value => true,
            TeamPermission::AssignCases->value => true,
            TeamPermission::ManageAppointments->value => true,
            TeamPermission::ManageTravel->value => true,
            TeamPermission::ManageTasks->value => true,
            TeamPermission::ManageCommunications->value => true,
            TeamPermission::ManageOrganizationSettings->value => false,
            TeamPermission::ApproveClinical->value => false,
        ],
    ],
    'doctor can approve clinical content but not manage organization' => [
        TeamRole::Doctor,
        [
            TeamPermission::ApproveClinical->value => true,
            TeamPermission::ReviewCases->value => true,
            TeamPermission::ManageCases->value => false,
            TeamPermission::ManageOrganizationSettings->value => false,
        ],
    ],
    'nurse reviews but cannot approve clinical content' => [
        TeamRole::Nurse,
        [
            TeamPermission::ReviewCases->value => true,
            TeamPermission::ApproveClinical->value => false,
            TeamPermission::ManageOrganizationSettings->value => false,
        ],
    ],
    'management viewer only sees reports' => [
        TeamRole::ManagementViewer,
        [
            TeamPermission::ViewReports->value => true,
            TeamPermission::ViewCases->value => false,
            TeamPermission::ManageOrganizationSettings->value => false,
        ],
    ],
]);

test('assignable roles exclude owner and include careflow clinical roles', function () {
    $values = collect(TeamRole::assignable())->pluck('value')->all();

    expect($values)
        ->not->toContain(TeamRole::Owner->value)
        ->toContain(TeamRole::Coordinator->value)
        ->toContain(TeamRole::Doctor->value)
        ->toContain(TeamRole::Nurse->value)
        ->toContain(TeamRole::ManagementViewer->value);
});
