<?php

namespace App\Http\Requests\Organizations;

use App\Enums\AppointmentConfirmationSource;
use App\Models\PatientCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionCaseAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PatientCase $case */
        $case = $this->route('case');

        return $this->user()?->can('manageAppointments', $case) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in([
                'request_confirmation',
                'confirm',
                'reschedule',
                'cancel',
                'complete',
            ])],
            'confirmation_source' => ['nullable', Rule::enum(AppointmentConfirmationSource::class)],
            'hospital_slot_verified' => ['nullable', 'boolean'],
            'confirmed_starts_at' => ['nullable', 'date'],
            'confirmed_ends_at' => ['nullable', 'date', 'after:confirmed_starts_at'],
            'external_his_reference' => ['nullable', 'string', 'max:255'],
            'proposed_starts_at' => ['nullable', 'date'],
            'proposed_ends_at' => ['nullable', 'date', 'after:proposed_starts_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
