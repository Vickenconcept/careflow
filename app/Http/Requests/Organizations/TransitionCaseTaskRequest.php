<?php

namespace App\Http\Requests\Organizations;

use App\Enums\CaseTaskStatus;
use App\Models\PatientCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionCaseTaskRequest extends FormRequest
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
            'status' => ['required', Rule::enum(CaseTaskStatus::class)],
            'comment' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
