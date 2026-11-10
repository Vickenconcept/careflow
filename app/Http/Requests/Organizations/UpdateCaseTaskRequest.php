<?php

namespace App\Http\Requests\Organizations;

use App\Enums\CaseTaskPriority;
use App\Models\PatientCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PatientCase $case */
        $case = $this->route('case');

        return $this->user()?->can('manageTasks', $case) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['nullable', Rule::enum(CaseTaskPriority::class)],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
