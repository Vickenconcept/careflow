<?php

namespace App\Http\Requests\Organizations;

use App\Models\PatientCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApproveCaseCommunicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PatientCase $case */
        $case = $this->route('case');

        return $this->user()?->can('manageCommunications', $case) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mark_sent' => ['nullable', 'boolean'],
        ];
    }
}
