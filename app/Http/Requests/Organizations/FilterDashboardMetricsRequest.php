<?php

namespace App\Http\Requests\Organizations;

use App\Enums\CaseStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterDashboardMetricsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::enum(CaseStatus::class)],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'country' => ['nullable', 'string', 'max:2'],
            'language' => ['nullable', 'string', 'max:8'],
            'coordinator_id' => ['nullable', 'integer', 'exists:users,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array{
     *     date_from: string|null,
     *     date_to: string|null,
     *     status: string|null,
     *     department_id: int|null,
     *     country: string|null,
     *     language: string|null,
     *     coordinator_id: int|null,
     *     doctor_id: int|null
     * }
     */
    public function filters(): array
    {
        return [
            'date_from' => $this->validated('date_from'),
            'date_to' => $this->validated('date_to'),
            'status' => $this->validated('status'),
            'department_id' => $this->validated('department_id') !== null
                ? (int) $this->validated('department_id')
                : null,
            'country' => $this->validated('country'),
            'language' => $this->validated('language'),
            'coordinator_id' => $this->validated('coordinator_id') !== null
                ? (int) $this->validated('coordinator_id')
                : null,
            'doctor_id' => $this->validated('doctor_id') !== null
                ? (int) $this->validated('doctor_id')
                : null,
        ];
    }
}
