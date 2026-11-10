<?php

namespace App\Http\Requests\Organizations;

use App\Models\PatientDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CorrectDocumentExtractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PatientDocument $document */
        $document = $this->route('document');

        return $this->user()?->can('reviewAi', $document) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'corrected_payload' => ['nullable', 'array'],
            'corrected_sections' => ['nullable', 'array'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
