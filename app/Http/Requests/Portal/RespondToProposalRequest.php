<?php

namespace App\Http\Requests\Portal;

use App\Enums\TreatmentProposalPatientResponse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RespondToProposalRequest extends FormRequest
{
    /**
     * Access is checked against the case's secure portal token in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'response' => ['required', Rule::enum(TreatmentProposalPatientResponse::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
