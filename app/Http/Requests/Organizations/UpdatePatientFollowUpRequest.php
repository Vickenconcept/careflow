<?php

namespace App\Http\Requests\Organizations;

use App\Enums\FollowUpStatus;
use App\Enums\FollowUpType;
use App\Models\PatientCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientFollowUpRequest extends FormRequest
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
            'follow_up_type' => ['sometimes', 'required', Rule::enum(FollowUpType::class)],
            'due_at' => ['sometimes', 'required', 'date'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', Rule::enum(FollowUpStatus::class)],
        ];
    }
}
