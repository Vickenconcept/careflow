<?php

namespace App\Ai\Tools\Assistant;

use App\Actions\Patients\CreateCaseTask;
use App\Enums\CaseTaskPriority;
use App\Enums\TeamPermission;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class CreateTask extends ApprovalRequiredTool
{
    public static function label(): string
    {
        return 'Create a task';
    }

    public static function requiredPermission(): ?TeamPermission
    {
        return TeamPermission::ManageTasks;
    }

    public function description(): Stringable|string
    {
        return 'Create a task on a case, optionally assigned to a staff member with a due date. The user must approve before it runs.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'reference' => $schema->string()->description('The case reference.')->required(),
            'title' => $schema->string()->description('What needs to be done, in a few words.')->required(),
            'description' => $schema->string()->description('Optional details.')->nullable(),
            'priority' => $schema->string()->enum(array_map(fn (CaseTaskPriority $priority) => $priority->value, CaseTaskPriority::cases()))->nullable(),
            'due_date' => $schema->string()->description('Due date as YYYY-MM-DD.')->nullable(),
            'assigned_user_id' => $schema->integer()->description('User id of the person who should do it. Defaults to the user.')->nullable(),
        ];
    }

    public function describe(Request $request): string
    {
        $assignee = $this->memberName($request['assigned_user_id'] ?? null) ?? $this->user->name;
        $due = $this->dueDate($request['due_date'] ?? null);

        return sprintf(
            'Create the task "%s" on case %s for %s%s.',
            (string) ($request['title'] ?? ''),
            strtoupper((string) ($request['reference'] ?? '')),
            $assignee,
            $due ? ', due '.$due->toFormattedDateString() : '',
        );
    }

    public function handle(Request $request): Stringable|string
    {
        $reference = (string) ($request['reference'] ?? '');
        $case = $this->findCase($reference);

        if ($case === null) {
            return $this->notFound($reference);
        }

        if (! Gate::forUser($this->user)->allows('manageTasks', $case)) {
            return $this->json(['error' => 'You are not allowed to add tasks to this case.']);
        }

        $title = trim((string) ($request['title'] ?? ''));

        if ($title === '') {
            return $this->json(['error' => 'A task needs a title.']);
        }

        $assignee = $this->member($request['assigned_user_id'] ?? null);
        $priority = CaseTaskPriority::tryFrom((string) ($request['priority'] ?? '')) ?? CaseTaskPriority::Normal;

        try {
            $task = app(CreateCaseTask::class)->handle($case, $this->user, [
                'title' => mb_substr($title, 0, 255),
                'description' => $request['description'] ?? null,
                'priority' => $priority->value,
                'due_at' => $this->dueDate($request['due_date'] ?? null),
                'assigned_user_id' => $assignee?->id ?? $this->user->id,
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()]);
        }

        return $this->json([
            'done' => true,
            'task' => $task->title,
            'reference' => $case->reference,
            'url' => $this->caseUrl($case, 'tasks'),
        ]);
    }

    private function dueDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->endOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
