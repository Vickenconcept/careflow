<?php

namespace App\Http\Requests\Organizations;

use App\Enums\CasePriority;
use App\Enums\CaseStatus;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterPatientCasesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Team $team */
        $team = $this->route('current_team');

        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::enum(CaseStatus::class)],
            'priority' => ['nullable', 'string', Rule::enum(CasePriority::class)],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(
                    fn ($query) => $query->where('team_id', $team->id)->whereNull('deleted_at'),
                ),
            ],
            'assignment' => ['nullable', 'string', Rule::in(['mine', 'unassigned', 'unassigned_doctor'])],
            'sort' => ['nullable', 'string', Rule::in(['recent', 'oldest', 'reference', 'status'])],
        ];
    }

    /**
     * @return array{
     *     search: string|null,
     *     status: string|null,
     *     priority: string|null,
     *     department_id: int|null,
     *     assignment: string|null,
     *     sort: string
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'search' => $validated['search'] ?? null,
            'status' => $validated['status'] ?? null,
            'priority' => $validated['priority'] ?? null,
            'department_id' => isset($validated['department_id']) ? (int) $validated['department_id'] : null,
            'assignment' => $validated['assignment'] ?? null,
            'sort' => $validated['sort'] ?? 'recent',
        ];
    }
}
