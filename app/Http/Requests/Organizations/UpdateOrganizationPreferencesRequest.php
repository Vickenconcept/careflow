<?php

namespace App\Http\Requests\Organizations;

use App\Models\OrganizationSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageSettings', $this->route('current_team')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'modules' => ['required', 'array'],
            'notifications' => ['required', 'array'],
        ];

        foreach (array_keys(OrganizationSetting::optionalModules()) as $key) {
            $rules["modules.$key"] = ['required', 'boolean'];
        }

        foreach (array_keys(OrganizationSetting::notificationOptions()) as $key) {
            $rules["notifications.$key"] = ['required', 'boolean'];
        }

        return $rules;
    }
}
