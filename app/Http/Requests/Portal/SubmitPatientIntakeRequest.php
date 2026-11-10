<?php

namespace App\Http\Requests\Portal;

use App\Enums\DocumentCategory;
use App\Enums\PatientGender;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitPatientIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->organization() instanceof Team;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->organization();
        $maxKilobytes = (int) config('careflow.documents.max_upload_kilobytes', 10240);
        $mimes = implode(',', config('careflow.documents.allowed_mimes', ['pdf', 'jpg', 'jpeg', 'png']));
        $languages = config('careflow.portal.supported_languages', ['en']);

        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::enum(PatientGender::class)],
            'country_of_residence' => ['nullable', 'string', 'size:2'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'preferred_language' => ['required', 'string', Rule::in($languages)],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(
                    fn ($query) => $query
                        ->where('team_id', $team->id)
                        ->where('is_active', true)
                        ->whereNull('deleted_at'),
                ),
            ],
            'reason_for_contact' => ['required', 'string', 'max:2000'],
            'diagnosis' => ['nullable', 'string', 'max:5000'],
            'symptoms_or_history' => ['nullable', 'string', 'max:5000'],
            'previous_procedures' => ['nullable', 'string', 'max:5000'],
            'current_medications' => ['nullable', 'string', 'max:5000'],
            'known_allergies' => ['nullable', 'string', 'max:5000'],
            'additional_comments' => ['nullable', 'string', 'max:5000'],
            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*' => ['file', 'mimes:'.$mimes, 'max:'.$maxKilobytes],
            'document_categories' => ['nullable', 'array'],
            'document_categories.*' => ['nullable', Rule::enum(DocumentCategory::class)],
            'enquiry_consent' => ['accepted'],
            'health_data_consent' => ['accepted'],
            'ai_processing_consent' => ['sometimes', 'boolean'],
        ];
    }

    public function organization(): Team
    {
        /** @var Team $team */
        $team = $this->route('organization');

        return $team;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'enquiry_consent.accepted' => 'You must accept the enquiry privacy notice.',
            'health_data_consent.accepted' => 'You must consent to health data processing for this enquiry.',
        ];
    }
}
