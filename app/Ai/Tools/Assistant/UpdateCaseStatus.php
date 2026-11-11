<?php

namespace App\Ai\Tools\Assistant;

use App\Actions\Patients\TransitionPatientCaseStatus;
use App\Enums\CaseStatus;
use App\Enums\TeamPermission;
use App\Exceptions\InvalidCaseStatusTransitionException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateCaseStatus extends ApprovalRequiredTool
{
    public static function label(): string
    {
        return 'Change a case status';
    }

    public static function requiredPermission(): ?TeamPermission
    {
        return TeamPermission::UpdateCaseStatus;
    }

    public function description(): Stringable|string
    {
        return 'Move a case to a new status. Read the case first and only choose one of its "can_move_to" statuses. The user must approve before it runs.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'reference' => $schema->string()->description('The case reference.')->required(),
            'status' => $schema->string()->enum(array_map(fn (CaseStatus $status) => $status->value, CaseStatus::cases()))->description('The new status.')->required(),
            'note' => $schema->string()->description('Optional short reason recorded on the timeline.')->nullable(),
        ];
    }

    public function describe(Request $request): string
    {
        $status = CaseStatus::tryFrom((string) ($request['status'] ?? ''));

        return sprintf(
            'Move case %s to "%s"%s.',
            strtoupper((string) ($request['reference'] ?? '')),
            $status?->label() ?? (string) ($request['status'] ?? ''),
            filled($request['note'] ?? null) ? ' with the note: '.$request['note'] : '',
        );
    }

    public function handle(Request $request): Stringable|string
    {
        $reference = (string) ($request['reference'] ?? '');
        $case = $this->findCase($reference);
        $status = CaseStatus::tryFrom((string) ($request['status'] ?? ''));

        if ($case === null) {
            return $this->notFound($reference);
        }

        if ($status === null) {
            return $this->json(['error' => 'That is not a known case status.']);
        }

        if (! Gate::forUser($this->user)->allows('updateStatus', $case)) {
            return $this->json(['error' => 'You are not allowed to change the status of this case.']);
        }

        try {
            app(TransitionPatientCaseStatus::class)->handle(
                case: $case,
                to: $status,
                actor: $this->user,
                note: filled($request['note'] ?? null) ? (string) $request['note'] : null,
            );
        } catch (InvalidCaseStatusTransitionException $exception) {
            return $this->json(['error' => $exception->getMessage()]);
        }

        return $this->json([
            'done' => true,
            'reference' => $case->reference,
            'status' => $status->label(),
            'url' => $this->caseUrl($case),
        ]);
    }
}
