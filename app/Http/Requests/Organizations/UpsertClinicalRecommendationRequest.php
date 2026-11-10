<?php

namespace App\Http\Requests\Organizations;

use App\Models\PatientCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpsertClinicalRecommendationRequest extends FormRequest
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
            'recommendation_text' => ['required', 'string', 'max:20000'],
            'supporting_document_uuids' => ['nullable', 'array'],
            'supporting_document_uuids.*' => ['uuid'],
            'submit_for_approval' => ['sometimes', 'boolean'],
        ];
    }
}
