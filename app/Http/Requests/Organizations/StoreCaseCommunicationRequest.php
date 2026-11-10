<?php

namespace App\Http\Requests\Organizations;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationDirection;
use App\Models\PatientCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseCommunicationRequest extends FormRequest
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
            'channel' => ['required', Rule::enum(CommunicationChannel::class)],
            'direction' => ['nullable', Rule::enum(CommunicationDirection::class)],
            'language' => ['nullable', 'string', 'max:10'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
            'recipient' => ['nullable', 'string', 'max:255'],
            'draft_with_ai' => ['nullable', 'boolean'],
            'ai_brief' => ['nullable', 'string', 'max:5000'],
            'log_only' => ['nullable', 'boolean'],
        ];
    }
}
