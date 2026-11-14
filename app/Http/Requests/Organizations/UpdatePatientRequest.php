<?php

namespace App\Http\Requests\Organizations;

use App\Enums\PatientGender;
use App\Enums\TeamPermission;
use App\Models\Patient;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Team $team */
        $team = $this->route('current_team');

        return $this->user()?->hasTeamPermission($team, TeamPermission::ManageCases) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Team $team */
        $team = $this->route('current_team');
        /** @var Patient $patient */
        $patient = $this->route('patient');

        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('patients', 'email')->where('team_id', $team->id)->ignore($patient->id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::enum(PatientGender::class)],
            'country_of_residence' => ['nullable', 'string', 'size:2'],
            'preferred_language' => ['required', 'string', Rule::in(config('careflow.portal.supported_languages', ['en']))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Another patient at this hospital already uses this email address.',
        ];
    }
}
