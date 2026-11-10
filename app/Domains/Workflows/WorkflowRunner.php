<?php

namespace App\Domains\Workflows;

use App\Actions\Organizations\EnsureDefaultMessageTemplates;
use App\Actions\Organizations\EnsureDefaultWorkflowDefinitions;
use App\Actions\Patients\CreateCaseCommunication;
use App\Actions\Patients\CreateCaseTask;
use App\Actions\Patients\CreatePatientFollowUp;
use App\Actions\Patients\RecordCaseActivity;
use App\Domains\Communications\MessageTemplateRenderer;
use App\Enums\CaseActivityType;
use App\Enums\CaseTaskPriority;
use App\Enums\CommunicationChannel;
use App\Enums\FollowUpType;
use App\Enums\MessageTemplateKey;
use App\Enums\WorkflowExecutionStatus;
use App\Enums\WorkflowKey;
use App\Enums\WorkflowStepStatus;
use App\Models\MessageTemplate;
use App\Models\PatientCase;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowExecution;
use App\Models\WorkflowExecutionStep;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class WorkflowRunner
{
    public function __construct(
        private EnsureDefaultWorkflowDefinitions $ensureWorkflows,
        private EnsureDefaultMessageTemplates $ensureTemplates,
        private CreateCaseTask $createCaseTask,
        private CreateCaseCommunication $createCaseCommunication,
        private CreatePatientFollowUp $createPatientFollowUp,
        private MessageTemplateRenderer $templateRenderer,
        private RecordCaseActivity $recordActivity,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function run(
        PatientCase $case,
        WorkflowKey $workflowKey,
        string $idempotencyKey,
        array $context = [],
        ?User $actor = null,
    ): WorkflowExecution {
        $case->loadMissing(['team', 'patient', 'assignedCoordinator', 'assignedDoctor']);

        $this->ensureWorkflows->handle($case->team);
        $this->ensureTemplates->handle($case->team);

        $definition = WorkflowDefinition::query()
            ->where('team_id', $case->team_id)
            ->where('key', $workflowKey->value)
            ->first();

        if ($definition === null || ! $definition->is_enabled) {
            return $this->storeSkipped($case, $workflowKey, $idempotencyKey, $context, $definition?->id, 'Workflow disabled or missing.');
        }

        try {
            $execution = WorkflowExecution::query()->create([
                'team_id' => $case->team_id,
                'patient_case_id' => $case->id,
                'workflow_definition_id' => $definition->id,
                'uuid' => (string) Str::uuid(),
                'workflow_key' => $workflowKey,
                'idempotency_key' => $idempotencyKey,
                'status' => WorkflowExecutionStatus::Running,
                'context' => $context,
                'started_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return WorkflowExecution::query()
                ->where('team_id', $case->team_id)
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail();
        }

        $systemActor = $actor ?? $case->assignedCoordinator ?? $this->resolveSystemActor($case);

        try {
            DB::transaction(function () use ($execution, $case, $systemActor, $definition): void {
                foreach (array_values($definition->steps ?? []) as $index => $stepConfig) {
                    $this->runStep(
                        execution: $execution,
                        case: $case,
                        actor: $systemActor,
                        stepConfig: is_array($stepConfig) ? $stepConfig : [],
                        sortOrder: $index + 1,
                    );
                }

                $execution->update([
                    'status' => WorkflowExecutionStatus::Completed,
                    'finished_at' => now(),
                    'error_message' => null,
                ]);
            });
        } catch (Throwable $exception) {
            $execution->update([
                'status' => WorkflowExecutionStatus::Failed,
                'finished_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);

            Log::warning('Workflow execution failed', [
                'workflow_key' => $workflowKey->value,
                'case_id' => $case->id,
                'team_id' => $case->team_id,
                'idempotency_key' => $idempotencyKey,
                'exception' => $exception->getMessage(),
            ]);
        }

        return $execution->fresh(['steps']);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function storeSkipped(
        PatientCase $case,
        WorkflowKey $workflowKey,
        string $idempotencyKey,
        array $context,
        ?int $definitionId,
        string $reason,
    ): WorkflowExecution {
        try {
            return WorkflowExecution::query()->create([
                'team_id' => $case->team_id,
                'patient_case_id' => $case->id,
                'workflow_definition_id' => $definitionId,
                'uuid' => (string) Str::uuid(),
                'workflow_key' => $workflowKey,
                'idempotency_key' => $idempotencyKey,
                'status' => WorkflowExecutionStatus::Skipped,
                'context' => $context,
                'started_at' => now(),
                'finished_at' => now(),
                'error_message' => $reason,
            ]);
        } catch (UniqueConstraintViolationException) {
            return WorkflowExecution::query()
                ->where('team_id', $case->team_id)
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail();
        }
    }

    /**
     * @param  array<string, mixed>  $stepConfig
     */
    protected function runStep(
        WorkflowExecution $execution,
        PatientCase $case,
        ?User $actor,
        array $stepConfig,
        int $sortOrder,
    ): void {
        $stepKey = (string) ($stepConfig['key'] ?? 'step_'.$sortOrder);
        $action = (string) ($stepConfig['action'] ?? '');

        $step = WorkflowExecutionStep::query()->create([
            'team_id' => $case->team_id,
            'workflow_execution_id' => $execution->id,
            'step_key' => $stepKey,
            'sort_order' => $sortOrder,
            'action' => $action,
            'status' => WorkflowStepStatus::Running,
            'config' => $stepConfig,
            'started_at' => now(),
        ]);

        try {
            $result = match ($action) {
                'create_task' => $this->executeCreateTask($case, $actor, $stepConfig),
                'create_follow_up' => $this->executeCreateFollowUp($case, $actor, $stepConfig),
                'draft_communication_from_template' => $this->executeDraftCommunication($case, $actor, $stepConfig),
                'notify_staff' => $this->executeNotifyStaff($case, $actor, $stepConfig),
                default => throw new InvalidArgumentException("Unsupported workflow action [{$action}]."),
            };

            $step->update([
                'status' => WorkflowStepStatus::Completed,
                'result' => $result,
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $step->update([
                'status' => WorkflowStepStatus::Failed,
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function executeCreateTask(PatientCase $case, ?User $actor, array $config): array
    {
        if ($actor === null) {
            throw new RuntimeException('No actor available to create workflow task.');
        }

        $assignee = match ($config['assign_to'] ?? 'coordinator') {
            'doctor' => $case->assignedDoctor,
            default => $case->assignedCoordinator,
        };

        $dueInHours = (int) ($config['due_in_hours'] ?? 24);

        $task = $this->createCaseTask->handle($case, $actor, [
            'title' => (string) ($config['title'] ?? 'Workflow task'),
            'description' => $config['description'] ?? null,
            'assigned_user_id' => $assignee?->id,
            'priority' => $config['priority'] ?? CaseTaskPriority::Normal->value,
            'due_at' => now()->addHours(max(1, $dueInHours))->toDateTimeString(),
        ]);

        return [
            'task_uuid' => $task->uuid,
            'assigned_user_id' => $task->assigned_user_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function executeCreateFollowUp(PatientCase $case, ?User $actor, array $config): array
    {
        if ($actor === null) {
            throw new RuntimeException('No actor available to create workflow follow-up.');
        }

        $dueInDays = (int) ($config['due_in_days'] ?? 7);

        $followUp = $this->createPatientFollowUp->handle($case, $actor, [
            'title' => (string) ($config['title'] ?? 'Post-treatment follow-up'),
            'description' => $config['description'] ?? null,
            'follow_up_type' => $config['follow_up_type'] ?? FollowUpType::Administrative->value,
            'due_at' => now()->addDays(max(1, $dueInDays))->toDateTimeString(),
            'assigned_user_id' => $case->assigned_coordinator_id,
        ]);

        return [
            'follow_up_uuid' => $followUp->uuid,
            'due_at' => $followUp->due_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function executeDraftCommunication(PatientCase $case, ?User $actor, array $config): array
    {
        if ($actor === null) {
            throw new RuntimeException('No actor available to draft workflow communication.');
        }

        $templateKey = MessageTemplateKey::from((string) $config['template_key']);
        $template = MessageTemplate::query()
            ->where('team_id', $case->team_id)
            ->where('key', $templateKey->value)
            ->where('is_active', true)
            ->firstOrFail();

        $language = $case->patient?->preferred_language ?? 'en';
        $rendered = $this->templateRenderer->render($template, $language, [
            'patient_name' => $case->patient?->fullName() ?? 'Patient',
            'case_reference' => $case->reference,
            'organization_name' => $case->team->name,
        ]);

        $requiresApproval = (bool) ($config['requires_approval'] ?? true);

        $communication = $this->createCaseCommunication->handle($case, $actor, [
            'channel' => $template->channel->value ?? CommunicationChannel::Email->value,
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
            'language' => $rendered['language'],
            'recipient' => $case->patient?->email,
            'log_only' => ! $requiresApproval,
        ]);

        return [
            'communication_uuid' => $communication->uuid,
            'template_key' => $templateKey->value,
            'status' => $communication->status->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function executeNotifyStaff(PatientCase $case, ?User $actor, array $config): array
    {
        $audience = (string) ($config['audience'] ?? 'coordinator');
        $summary = (string) ($config['summary'] ?? 'Workflow notification');

        $this->recordActivity->handle(
            case: $case,
            type: CaseActivityType::NoteAdded,
            summary: $summary,
            actor: $actor,
            properties: [
                'notification' => true,
                'audience' => $audience,
                'workflow' => true,
            ],
        );

        return [
            'audience' => $audience,
            'summary' => $summary,
            'delivered' => 'case_activity',
        ];
    }

    protected function resolveSystemActor(PatientCase $case): ?User
    {
        return $case->team->members()
            ->orderBy('users.id')
            ->first();
    }
}
