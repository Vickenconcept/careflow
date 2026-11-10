<?php

namespace App\Http\Requests\Organizations;

use App\Enums\ClinicalApprovalOutcome;
use App\Models\PatientCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideClinicalRecommendationRequest extends FormRequest
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
            'outcome' => ['required', 'string', Rule::enum(ClinicalApprovalOutcome::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
