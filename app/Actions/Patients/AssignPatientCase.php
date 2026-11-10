<?php

namespace App\Actions\Patients;

use App\Domains\Workflows\WorkflowRunner;
use App\Enums\CaseActivityType;
use App\Enums\TeamRole;
use App\Enums\WorkflowKey;
use App\Events\Patients\CaseAssigned;
use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AssignPatientCase
{
    public function __construct(
        private RecordCaseActivity $recordActivity,
        private WorkflowRunner $workflows,
    ) {}

    public function handle(
        PatientCase $case,
        User $actor,
        ?User $coordinator = null,
        ?User $doctor = null,
        bool $clearCoordinator = false,
        bool $clearDoctor = false,
    ): PatientCase {
        $doctorAssignedId = null;
        $notifiedCoordinatorId = null;
        $notifiedDoctorId = null;

        $result = DB::transaction(function () use ($case, $actor, $coordinator, $doctor, $clearCoordinator, $clearDoctor, &$doctorAssignedId, &$notifiedCoordinatorId, &$notifiedDoctorId) {
            /** @var PatientCase $locked */
            $locked = PatientCase::query()
                ->whereKey($case->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($coordinator !== null) {
                $this->assertTeamRole($locked, $coordinator, [
                    TeamRole::Owner,
                    TeamRole::Admin,
                    TeamRole::Coordinator,
                ], 'coordinator');

                $previousCoordinatorId = $locked->assigned_coordinator_id;
                $locked->assigned_coordinator_id = $coordinator->id;

                $this->recordActivity->handle(
                    case: $locked,
                    type: CaseActivityType::CoordinatorAssigned,
                    summary: sprintf('Coordinator assigned: %s', $coordinator->name),
                    actor: $actor,
                    properties: [
                        'coordinator_id' => $coordinator->id,
                        'coordinator_name' => $coordinator->name,
                    ],
                );

                if ($previousCoordinatorId !== $coordinator->id) {
                    $notifiedCoordinatorId = $coordinator->id;
                }
            } elseif ($clearCoordinator) {
                $locked->assigned_coordinator_id = null;

                $this->recordActivity->handle(
                    case: $locked,
                    type: CaseActivityType::AssignmentCleared,
                    summary: 'Coordinator assignment cleared',
                    actor: $actor,
                    properties: ['role' => 'coordinator'],
                );
            }

            if ($doctor !== null) {
                $this->assertTeamRole($locked, $doctor, [
                    TeamRole::Doctor,
                    TeamRole::Owner,
                    TeamRole::Admin,
                ], 'doctor');

                $previousDoctorId = $locked->assigned_doctor_id;
                $locked->assigned_doctor_id = $doctor->id;

                $this->recordActivity->handle(
                    case: $locked,
                    type: CaseActivityType::DoctorAssigned,
                    summary: sprintf('Doctor assigned: %s', $doctor->name),
                    actor: $actor,
                    properties: [
                        'doctor_id' => $doctor->id,
                        'doctor_name' => $doctor->name,
                    ],
                );

                if ($previousDoctorId !== $doctor->id) {
                    $doctorAssignedId = $doctor->id;
                    $notifiedDoctorId = $doctor->id;
                }
            } elseif ($clearDoctor) {
                $locked->assigned_doctor_id = null;

                $this->recordActivity->handle(
                    case: $locked,
                    type: CaseActivityType::AssignmentCleared,
                    summary: 'Doctor assignment cleared',
                    actor: $actor,
                    properties: ['role' => 'doctor'],
                );
            }

            $locked->last_activity_at = now();
            $locked->save();

            return $locked->fresh([
                'assignedCoordinator:id,name,email',
                'assignedDoctor:id,name,email',
            ]);
        });

        if ($doctorAssignedId !== null) {
            $this->workflows->run(
                case: $result,
                workflowKey: WorkflowKey::DoctorEvaluation,
                idempotencyKey: 'doctor_assigned:'.$result->id.':'.$doctorAssignedId,
                context: [
                    'doctor_id' => $doctorAssignedId,
                    'trigger' => WorkflowKey::DoctorEvaluation->trigger(),
                ],
                actor: $actor,
            );
        }

        if ($notifiedCoordinatorId !== null || $notifiedDoctorId !== null) {
            event(new CaseAssigned(
                caseId: $result->id,
                actorUserId: $actor->id,
                coordinatorUserId: $notifiedCoordinatorId,
                doctorUserId: $notifiedDoctorId,
            ));
        }

        return $result;
    }

    /**
     * @param  list<TeamRole>  $allowedRoles
     */
    protected function assertTeamRole(PatientCase $case, User $assignee, array $allowedRoles, string $label): void
    {
        if (! $assignee->belongsToTeam($case->team)) {
            throw new InvalidArgumentException("Selected {$label} is not a member of this organization.");
        }

        $role = $assignee->teamRole($case->team);

        if ($role === null || ! in_array($role, $allowedRoles, true)) {
            throw new InvalidArgumentException("Selected user cannot be assigned as {$label}.");
        }
    }
}
