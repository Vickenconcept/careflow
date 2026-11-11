<?php

namespace App\Http\Requests\Organizations;

use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAssistantSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Team $team */
        $team = $this->route('current_team');

        return $this->user()?->can('manageSettings', $team) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assistant_enabled' => ['sometimes', 'boolean'],
            'assistant_name' => ['required', 'string', 'max:40'],
            'assistant_knowledge' => ['nullable', 'string', 'max:6000'],
        ];
    }
}
