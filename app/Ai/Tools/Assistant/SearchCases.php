<?php

namespace App\Ai\Tools\Assistant;

use App\Enums\CaseStatus;
use App\Models\PatientCase;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchCases extends AssistantTool
{
    public static function label(): string
    {
        return 'Search cases';
    }

    public function description(): Stringable|string
    {
        return 'Find patient cases the user can see, by patient name, case reference, status or assignment. Returns up to 15 cases, newest activity first.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Patient name, email or case reference to look for. Leave empty to list cases.')->nullable(),
            'status' => $schema->string()->enum(array_map(fn (CaseStatus $status) => $status->value, CaseStatus::cases()))->description('Only cases in this status.')->nullable(),
            'assignment' => $schema->string()->enum(['mine', 'unassigned', 'any'])->description('mine = assigned to the user, unassigned = no coordinator yet.')->nullable(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $query = trim((string) ($request['query'] ?? ''));
        $status = $request['status'] ?? null;
        $assignment = $request['assignment'] ?? 'any';

        $cases = $this->visibleCases()
            ->with(['patient', 'department', 'assignedCoordinator', 'assignedDoctor'])
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($inner) use ($query) {
                    $inner->where('patient_cases.reference', 'like', '%'.strtoupper($query).'%')
                        ->orWhereHas('patient', function ($patient) use ($query) {
                            $patient->where('first_name', 'like', "%{$query}%")
                                ->orWhere('last_name', 'like', "%{$query}%")
                                ->orWhere('email', 'like', "%{$query}%");
                        });
                });
            })
            ->when(is_string($status) && CaseStatus::tryFrom($status) !== null, fn ($builder) => $builder->where('patient_cases.status', $status))
            ->when($assignment === 'mine', fn ($builder) => $builder->where(function ($inner) {
                $inner->where('patient_cases.assigned_coordinator_id', $this->user->id)
                    ->orWhere('patient_cases.assigned_doctor_id', $this->user->id);
            }))
            ->when($assignment === 'unassigned', fn ($builder) => $builder->whereNull('patient_cases.assigned_coordinator_id'))
            ->orderByDesc('patient_cases.last_activity_at')
            ->limit(15)
            ->get();

        return $this->json([
            'count' => $cases->count(),
            'cases' => $cases->map(fn (PatientCase $case) => [
                'reference' => $case->reference,
                'patient' => $case->patient?->fullName(),
                'status' => $case->status->label(),
                'priority' => $case->priority->label(),
                'department' => $case->department?->name,
                'coordinator' => $case->assignedCoordinator?->name,
                'doctor' => $case->assignedDoctor?->name,
                'last_activity' => $case->last_activity_at?->diffForHumans(),
                'url' => $this->caseUrl($case),
            ])->all(),
        ]);
    }
}
