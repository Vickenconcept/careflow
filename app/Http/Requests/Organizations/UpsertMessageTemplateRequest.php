<?php

namespace App\Http\Requests\Organizations;

use App\Enums\CommunicationChannel;
use App\Enums\MessageTemplateKey;
use App\Enums\TeamPermission;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Team $team */
        $team = $this->route('current_team');

        return $this->user()?->belongsToTeam($team)
            && $this->user()->hasTeamPermission($team, TeamPermission::ManageCommunications);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', Rule::enum(MessageTemplateKey::class)],
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', Rule::enum(CommunicationChannel::class)],
            'is_active' => ['nullable', 'boolean'],
            'subject_translations' => ['nullable', 'array'],
            'subject_translations.*' => ['nullable', 'string', 'max:255'],
            'body_translations' => ['required', 'array', 'min:1'],
            'body_translations.*' => ['required', 'string', 'max:20000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
