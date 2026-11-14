<?php

namespace App\Http\Requests\Organizations;

use App\Enums\DocumentCategory;
use App\Models\PatientCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PatientCase $case */
        $case = $this->route('case');

        return $this->user()?->can('manage', $case) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxKilobytes = (int) config('careflow.documents.max_upload_kilobytes', 10240);
        $mimes = implode(',', config('careflow.documents.allowed_mimes', ['pdf', 'jpg', 'jpeg', 'png']));

        return [
            'documents' => ['required', 'array', 'min:1', 'max:10'],
            'documents.*' => ['file', 'mimes:'.$mimes, 'max:'.$maxKilobytes],
            'category' => ['nullable', Rule::enum(DocumentCategory::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'documents.required' => 'Choose at least one file.',
            'documents.*.mimes' => 'Only PDF, JPG and PNG files can be added.',
            'documents.*.max' => 'Each file must be smaller than '.(int) (config('careflow.documents.max_upload_kilobytes', 10240) / 1024).' MB.',
        ];
    }
}
