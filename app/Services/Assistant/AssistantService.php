<?php

namespace App\Services\Assistant;

use App\Ai\Agents\CareAssistantAgent;
use App\Ai\Tools\Assistant\ApprovalRequiredTool;
use App\Ai\Tools\Assistant\AssistantTool;
use App\Models\AssistantThread;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Models\ConversationMessage;

/**
 * Runs the assistant for one person inside one hospital: sends messages,
 * resolves approvals, and turns the stored conversation into chat state.
 */
class AssistantService
{
    public function __construct(private ConversationStore $store) {}

    public function isEnabled(Team $team): bool
    {
        return (bool) $team->ensureOrganizationSetting()->assistant_enabled;
    }

    public function conversationId(User $user, Team $team): ?string
    {
        return AssistantThread::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->value('conversation_id');
    }

    /**
     * Send a message and return the updated chat.
     *
     * @return array<string, mixed>
     */
    public function send(User $user, Team $team, string $message): array
    {
        $agent = (new CareAssistantAgent($user, $team))
            ->continueOrStart($this->conversationId($user, $team), as: $user);

        $response = $agent->prompt($message);

        $this->remember($user, $team, $response->conversationId ?? $agent->currentConversation());

        return $this->state($user, $team);
    }

    /**
     * Approve or decline one action the assistant proposed, then let it continue.
     *
     * @return array<string, mixed>
     */
    public function decide(User $user, Team $team, string $toolCallId, bool $approve): array
    {
        $conversationId = $this->conversationId($user, $team);

        if ($conversationId === null || ! $this->hasPendingApproval($conversationId, $toolCallId)) {
            return $this->state($user, $team);
        }

        (new CareAssistantAgent($user, $team))
            ->continue($conversationId, as: $user)
            ->prompt(Decisions::from([
                $toolCallId => $approve
                    ? Decision::approve()
                    : Decision::reject('The user declined this action. Do not try it again unless they ask.'),
            ]));

        return $this->state($user, $team);
    }

    /**
     * Start a fresh conversation. The old one stays in storage but is no longer used.
     */
    public function clear(User $user, Team $team): void
    {
        AssistantThread::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->update(['conversation_id' => null]);
    }

    /**
     * The chat as the person should see it.
     *
     * @return array{messages: list<array<string, mixed>>, pending: list<array<string, mixed>>}
     */
    public function state(User $user, Team $team): array
    {
        $conversationId = $this->conversationId($user, $team);

        if ($conversationId === null) {
            return ['messages' => [], 'pending' => []];
        }

        $messages = ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->map(fn (ConversationMessage $message) => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => (string) $message->content,
                'actions' => $message->role === 'assistant' ? $this->actionsIn($message) : [],
                'created_at' => $message->created_at?->toIso8601String(),
            ])
            ->filter(fn (array $message) => trim($message['content']) !== '' || $message['actions'] !== [])
            ->values()
            ->all();

        $pending = array_map(fn (PendingApproval $approval) => [
            'id' => $approval->id,
            'tool' => $approval->tool,
            'label' => $this->toolLabel($approval->tool),
            'summary' => $approval->reason ?: $this->toolLabel($approval->tool),
        ], $this->store->pendingApprovalsFor($conversationId));

        return ['messages' => $messages, 'pending' => $pending];
    }

    /**
     * Tools this person can use, for the "what I can do" list.
     *
     * @return list<array{name: string, label: string, needs_approval: bool}>
     */
    public function capabilities(User $user, Team $team): array
    {
        return array_map(fn (string $tool) => [
            'name' => Str::snake(class_basename($tool)),
            'label' => $tool::label(),
            'needs_approval' => is_subclass_of($tool, ApprovalRequiredTool::class),
        ], CareAssistantAgent::toolClassesFor($user, $team));
    }

    private function remember(User $user, Team $team, ?string $conversationId): void
    {
        if ($conversationId === null) {
            return;
        }

        AssistantThread::query()->updateOrCreate(
            ['team_id' => $team->id, 'user_id' => $user->id],
            ['conversation_id' => $conversationId],
        );
    }

    private function hasPendingApproval(string $conversationId, string $toolCallId): bool
    {
        foreach ($this->store->pendingApprovalsFor($conversationId) as $approval) {
            if ($approval->id === $toolCallId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Completed tool calls in a turn, shown as small "done" or "declined" notes.
     *
     * @return list<array{label: string, outcome: string}>
     */
    private function actionsIn(ConversationMessage $message): array
    {
        $actions = [];

        foreach ($message->tool_results as $result) {
            $label = $this->toolLabel((string) ($result['name'] ?? ''));
            $outcome = match (true) {
                (bool) ($result['denied'] ?? false) => 'declined',
                (bool) ($result['failed'] ?? false) => 'failed',
                default => 'done',
            };

            $actions[] = ['label' => $label, 'outcome' => $outcome];
        }

        return $actions;
    }

    private function toolLabel(string $name): string
    {
        foreach (CareAssistantAgent::TOOLS as $tool) {
            /** @var class-string<AssistantTool> $tool */
            if (Str::snake(class_basename($tool)) === $name) {
                return $tool::label();
            }
        }

        return Str::headline($name);
    }
}
