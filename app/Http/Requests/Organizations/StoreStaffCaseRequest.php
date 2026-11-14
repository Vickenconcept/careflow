<?php

namespace App\Http\Requests\Organizations;

use App\Enums\PatientGender;
use App\Enums\TeamPermission;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffCaseRequest extends FormRequest
{
    /**
     * Where an enquiry entered by staff came from.
     *
     * @var array<string, string>
     */
    public const SOURCES = [
        'phone' => 'Phone call',
        'email' => 'Email',
        'whatsapp' => 'WhatsApp',
        'walk_in' => 'Walk-in',
        'agent' => 'Agent or partner referral',
        'other' => 'Other',
    ];

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

        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::enum(PatientGender::class)],
            'country_of_residence' => ['nullable', 'string', 'size:2'],
            'preferred_language' => ['required', 'string', Rule::in(config('careflow.portal.supported_languages', ['en']))],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(
                    fn ($query) => $query->where('team_id', $team->id)->where('is_active', true)->whereNull('deleted_at'),
                ),
            ],
            'source' => ['required', Rule::in(array_keys(self::SOURCES))],
            'reason_for_contact' => ['required', 'string', 'max:2000'],
            'diagnosis' => ['nullable', 'string', 'max:5000'],
            'additional_comments' => ['nullable', 'string', 'max:5000'],
            'consent_confirmed' => ['accepted'],
            'ai_processing_consent' => ['sometimes', 'boolean'],
            'email_patient' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent_confirmed.accepted' => 'Confirm that the patient agreed to the hospital handling their enquiry and health information.',
        ];
    }
}
