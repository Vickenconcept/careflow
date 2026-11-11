<?php

namespace App\Ai\Tools\Assistant;

use App\Actions\Patients\AssignPatientCase;
use App\Enums\TeamPermission;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Laravel\Ai\Tools\Request;
use Stringable;

class AssignCase extends ApprovalRequiredTool
{
    public static function label(): string
    {
        return 'Assign a case';
    }

    public static function requiredPermission(): ?TeamPermission
    {
        return TeamPermission::AssignCases;
    }

    public function description(): Stringable|string
    {
        return 'Assign a coordinator and/or a doctor to a case. Look up user ids with the staff directory first. The user must approve before it runs.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'reference' => $schema->string()->description('The case reference.')->required(),
            'coordinator_user_id' => $schema->integer()->description('User id of the coordinator to assign.')->nullable(),
            'doctor_user_id' => $schema->integer()->description('User id of the doctor to assign.')->nullable(),
        ];
    }

    public function describe(Request $request): string
    {
        $parts = array_filter([
            ($name = $this->memberName($request['coordinator_user_id'] ?? null)) ? "{$name} as coordinator" : null,
            ($name = $this->memberName($request['doctor_user_id'] ?? null)) ? "{$name} as doctor" : null,
        ]);

        return sprintf(
            'Assign %s on case %s.',
            $parts === [] ? 'staff' : implode(' and ', $parts),
            strtoupper((string) ($request['reference'] ?? '')),
        );
    }

    public function handle(Request $request): Stringable|string
    {
        $reference = (string) ($request['reference'] ?? '');
        $case = $this->findCase($reference);

        if ($case === null) {
            return $this->notFound($reference);
        }

        if (! Gate::forUser($this->user)->allows('assign', $case)) {
            return $this->json(['error' => 'You are not allowed to assign this case.']);
        }

        $coordinator = $this->member($request['coordinator_user_id'] ?? null);
        $doctor = $this->member($request['doctor_user_id'] ?? null);

        if ($coordinator === null && $doctor === null) {
            return $this->json(['error' => 'Name a coordinator or a doctor from this hospital to assign.']);
        }

        try {
            app(AssignPatientCase::class)->handle($case, $this->user, coordinator: $coordinator, doctor: $doctor);
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()]);
        }

        $case->refresh()->load(['assignedCoordinator', 'assignedDoctor']);

        return $this->json([
            'done' => true,
            'reference' => $case->reference,
            'coordinator' => $case->assignedCoordinator?->name,
            'doctor' => $case->assignedDoctor?->name,
            'url' => $this->caseUrl($case),
        ]);
    }
}
