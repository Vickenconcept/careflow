<?php

namespace App\Http\Requests\Organizations;

use App\Models\PatientCase;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignPatientCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PatientCase $case */
        $case = $this->route('case');

        return $this->user()?->can('assign', $case) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Team $team */
        $team = $this->route('current_team');

        return [
            'coordinator_id' => [
                'nullable',
                'integer',
                Rule::exists('team_members', 'user_id')->where(
                    fn ($query) => $query->where('team_id', $team->id),
                ),
            ],
            'doctor_id' => [
                'nullable',
                'integer',
                Rule::exists('team_members', 'user_id')->where(
                    fn ($query) => $query->where('team_id', $team->id),
                ),
            ],
            'clear_coordinator' => ['sometimes', 'boolean'],
            'clear_doctor' => ['sometimes', 'boolean'],
        ];
    }
}
