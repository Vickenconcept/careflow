<?php

namespace App\Http\Requests\Organizations;

use App\Enums\DoctorReviewOutcome;
use App\Models\PatientCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitDoctorReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PatientCase $case */
        $case = $this->route('case');

        return $this->user()?->can('approveClinical', $case) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'outcome' => ['required', 'string', Rule::enum(DoctorReviewOutcome::class)],
            'findings' => ['required', 'string', 'max:20000'],
            'additional_information_request' => [
                'nullable',
                'string',
                'max:10000',
                Rule::requiredIf(
                    fn () => $this->string('outcome')->toString() === DoctorReviewOutcome::NeedMoreInformation->value,
                ),
            ],
        ];
    }
}
