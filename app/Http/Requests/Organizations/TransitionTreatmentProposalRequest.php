<?php

namespace App\Http\Requests\Organizations;

use App\Enums\TreatmentProposalPatientResponse;
use App\Models\PatientCase;
use App\Models\TreatmentProposal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionTreatmentProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PatientCase $case */
        $case = $this->route('case');
        $action = (string) $this->input('action');

        return match ($action) {
            'submit_for_approval', 'send', 'record_patient_response' => $this->user()?->can('manageProposals', $case) ?? false,
            'approve', 'return_to_draft' => $this->user()?->can('approveProposals', $case) ?? false,
            default => false,
        };
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in([
                'submit_for_approval',
                'approve',
                'return_to_draft',
                'send',
                'record_patient_response',
            ])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'patient_response' => [
                'nullable',
                'string',
                Rule::enum(TreatmentProposalPatientResponse::class),
                Rule::requiredIf(fn () => $this->string('action')->toString() === 'record_patient_response'),
            ],
        ];
    }

    public function proposal(): TreatmentProposal
    {
        /** @var TreatmentProposal $proposal */
        $proposal = $this->route('proposal');

        return $proposal;
    }
}
