<?php

namespace App\Http\Requests\Organizations;

use App\Enums\TravelArrangementStatus;
use App\Enums\VisaAssistanceStatus;
use App\Models\PatientCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertTravelArrangementRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PatientCase $case */
        $case = $this->route('case');

        return $this->user()?->can('manageTravel', $case) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(TravelArrangementStatus::class)],
            'assigned_coordinator_id' => ['nullable', 'integer', 'exists:users,id'],
            'expected_arrival_date' => ['nullable', 'date'],
            'expected_departure_date' => ['nullable', 'date', 'after_or_equal:expected_arrival_date'],
            'flight_information' => ['nullable', 'string', 'max:5000'],
            'airport_pickup_required' => ['nullable', 'boolean'],
            'airport_pickup_details' => ['nullable', 'string', 'max:5000'],
            'transfer_arrangements' => ['nullable', 'string', 'max:5000'],
            'accommodation' => ['nullable', 'string', 'max:5000'],
            'hotel_details' => ['nullable', 'string', 'max:5000'],
            'visa_assistance_status' => ['nullable', Rule::enum(VisaAssistanceStatus::class)],
            'special_administrative_requirements' => ['nullable', 'string', 'max:5000'],
            'checklist' => ['nullable', 'array'],
            'checklist.*.key' => ['required_with:checklist', 'string', 'max:100'],
            'checklist.*.label' => ['required_with:checklist', 'string', 'max:255'],
            'checklist.*.done' => ['required_with:checklist', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
