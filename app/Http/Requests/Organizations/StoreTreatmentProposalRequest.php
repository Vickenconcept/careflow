<?php

namespace App\Http\Requests\Organizations;

use App\Models\PatientCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTreatmentProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PatientCase $case */
        $case = $this->route('case');

        return $this->user()?->can('manageProposals', $case) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'responsible_physician_id' => ['nullable', 'integer', 'exists:users,id'],
            'clinical_recommendation_id' => ['nullable', 'integer', 'exists:clinical_recommendations,id'],
            'proposed_procedure' => ['required', 'string', 'max:500'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'expected_duration' => ['nullable', 'string', 'max:255'],
            'required_appointments' => ['nullable', 'integer', 'min:0', 'max:100'],
            'additional_information' => ['nullable', 'string', 'max:10000'],
            'valid_until' => ['nullable', 'date', 'after:today'],
            'items' => ['nullable', 'array'],
            'items.*.description' => ['required_with:items', 'string', 'max:500'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
