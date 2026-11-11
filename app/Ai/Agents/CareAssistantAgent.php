<?php

namespace App\Ai\Agents;

use App\Ai\Tools\Assistant\AssignCase;
use App\Ai\Tools\Assistant\AssistantTool;
use App\Ai\Tools\Assistant\CreateTask;
use App\Ai\Tools\Assistant\GetCaseDetails;
use App\Ai\Tools\Assistant\HospitalDirectory;
use App\Ai\Tools\Assistant\ListMyWork;
use App\Ai\Tools\Assistant\SearchCases;
use App\Ai\Tools\Assistant\UpdateCaseStatus;
use App\Ai\Tools\Assistant\WorkloadSummary;
use App\Models\Team;
use App\Models\User;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * The hospital's AI operations assistant.
 *
 * Memory is personal (one conversation per person and hospital), while what it
 * knows comes from the hospital's own data, filtered by that person's access.
 */
#[Provider(Lab::OpenAI)]
#[MaxSteps(8)]
class CareAssistantAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    /**
     * Every tool the assistant can be given, in the order they are offered.
     *
     * @var list<class-string<AssistantTool>>
     */
    public const TOOLS = [
        SearchCases::class,
        GetCaseDetails::class,
        ListMyWork::class,
        WorkloadSummary::class,
        HospitalDirectory::class,
        AssignCase::class,
        UpdateCaseStatus::class,
        CreateTask::class,
    ];

    public function __construct(
        public readonly User $user,
        public readonly Team $team,
    ) {}

    /**
     * The tool classes this person is allowed to use in this hospital.
     *
     * @return list<class-string<AssistantTool>>
     */
    public static function toolClassesFor(User $user, Team $team): array
    {
        return array_values(array_filter(
            self::TOOLS,
            fn (string $tool) => $tool::requiredPermission() === null
                || $user->hasTeamPermission($team, $tool::requiredPermission()),
        ));
    }

    public function tools(): iterable
    {
        return array_map(
            fn (string $tool) => new $tool($this->user, $this->team),
            self::toolClassesFor($this->user, $this->team),
        );
    }

    public function instructions(): Stringable|string
    {
        $settings = $this->team->ensureOrganizationSetting();
        $name = $settings->assistant_name ?: 'Nora';
        $role = $this->user->teamRole($this->team)?->label() ?? 'staff member';
        $knowledge = trim((string) $settings->assistant_knowledge);

        $lines = [
            "You are {$name}, the operations assistant for the international patient department of {$this->team->name}.",
            "You are speaking with {$this->user->name}, whose role is {$role}. Today is ".now()->toFormattedDateString().'.',
            '',
            'How you work:',
            '- Answer from the tools. Never invent patients, cases, numbers, staff or dates. If a tool returns nothing, say so.',
            "- You only see what {$this->user->name} is allowed to see. Never mention data from other hospitals.",
            '- Actions that change data (assigning, changing a status, creating a task) are proposed through tools and wait for the user to approve them. Say what you are proposing in one sentence and let the approval card do the rest. Never claim an action was done unless the tool result says "done".',
            '- Before assigning staff, look up their user ids with the staff directory. Before changing a status, read the case and pick one of its allowed next statuses.',
            '- You do not give medical advice, diagnoses or treatment decisions. Clinical judgement belongs to the doctors; you can point to where their notes are.',
            '- Keep replies short and practical. Use plain sentences and short lists.',
            '- Mention cases by their reference, like CF-2026-000012. References become clickable automatically, so do not paste URLs or markdown links.',
        ];

        if ($knowledge !== '') {
            $lines[] = '';
            $lines[] = "Hospital knowledge provided by {$this->team->name}'s administrators (use it to answer policy and process questions):";
            $lines[] = mb_substr($knowledge, 0, 6000);
        }

        return implode("\n", $lines);
    }

    protected function maxConversationMessages(): int
    {
        return 40;
    }
}
