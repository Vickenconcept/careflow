<?php

namespace App\Http\Requests\Portal;

use App\Enums\DocumentCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadPortalDocumentsRequest extends FormRequest
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
        $maxKilobytes = (int) config('careflow.documents.max_upload_kilobytes', 10240);
        $mimes = implode(',', config('careflow.documents.allowed_mimes', ['pdf', 'jpg', 'jpeg', 'png']));

        return [
            'token' => ['required', 'string'],
            'documents' => ['required', 'array', 'min:1', 'max:10'],
            'documents.*' => ['file', 'mimes:'.$mimes, 'max:'.$maxKilobytes],
            'document_categories' => ['nullable', 'array'],
            'document_categories.*' => ['nullable', Rule::enum(DocumentCategory::class)],
        ];
    }
}
