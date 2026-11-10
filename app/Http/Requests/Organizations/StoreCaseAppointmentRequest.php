<?php

namespace App\Http\Requests\Organizations;

use App\Enums\AppointmentType;
use App\Models\PatientCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseAppointmentRequest extends FormRequest
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
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'appointment_type' => ['required', Rule::enum(AppointmentType::class)],
            'proposed_starts_at' => ['required', 'date'],
            'proposed_ends_at' => ['nullable', 'date', 'after:proposed_starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_instructions' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'external_his_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
