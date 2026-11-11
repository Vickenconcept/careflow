<?php

namespace App\Http\Requests\Organizations;

use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationAiRequest extends FormRequest
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
            'ai_processing_enabled' => ['required_without:assistant_enabled', 'boolean'],
            'assistant_enabled' => ['required_without:ai_processing_enabled', 'boolean'],
        ];
    }
}
