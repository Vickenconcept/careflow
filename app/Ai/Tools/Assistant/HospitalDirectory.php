<?php

namespace App\Ai\Tools\Assistant;

use App\Enums\TeamRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class HospitalDirectory extends AssistantTool
{
    public static function label(): string
    {
        return 'Staff and departments';
    }

    public function description(): Stringable|string
    {
        return 'List this hospital\'s departments and staff with their roles and user ids. Use it to find who can be assigned as coordinator or doctor before assigning a case or a task.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->json([
            'hospital' => $this->team->name,
            'departments' => $this->team->departments()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Department $department) => $department->name)
                ->all(),
            'staff' => $this->team->members()
                ->orderBy('name')
                ->get()
                ->map(function (User $member) {
                    $role = $member->pivot->role instanceof TeamRole
                        ? $member->pivot->role
                        : TeamRole::tryFrom((string) $member->pivot->role);

                    return [
                        'user_id' => $member->id,
                        'name' => $member->name,
                        'role' => $role?->label(),
                        'can_coordinate' => in_array($role, [TeamRole::Owner, TeamRole::Admin, TeamRole::Coordinator], true),
                        'can_be_case_doctor' => in_array($role, [TeamRole::Owner, TeamRole::Admin, TeamRole::Doctor], true),
                    ];
                })
                ->all(),
        ]);
    }
}
