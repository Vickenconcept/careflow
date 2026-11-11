<?php

namespace App\Http\Requests\Organizations;

use App\Enums\TeamPermission;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAssignCasesRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Team $team */
        $team = $this->route('current_team');

        return $this->user()?->hasTeamPermission($team, TeamPermission::AssignCases) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Team $team */
        $team = $this->route('current_team');
        $member = Rule::exists('team_members', 'user_id')->where(fn ($query) => $query->where('team_id', $team->id));

        return [
            'case_ids' => ['required', 'array', 'min:1', 'max:100'],
            'case_ids.*' => ['integer'],
            'coordinator_id' => ['nullable', 'integer', $member],
            'doctor_id' => ['nullable', 'integer', $member],
            'clear_coordinator' => ['sometimes', 'boolean'],
            'clear_doctor' => ['sometimes', 'boolean'],
        ];
    }
}
