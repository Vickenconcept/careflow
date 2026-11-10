<?php

namespace App\Actions\Patients;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\CaseActivityType;
use App\Models\CaseAppointment;
use App\Models\Department;
use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateCaseAppointment
{
    public function __construct(
        private RecordCaseActivity $recordActivity,
    ) {}

    /**
     * @param  array{
     *     department_id?: int|null,
     *     doctor_id?: int|null,
     *     appointment_type: string,
     *     proposed_starts_at: string,
     *     proposed_ends_at?: string|null,
     *     location?: string|null,
     *     meeting_instructions?: string|null,
     *     notes?: string|null,
     *     external_his_reference?: string|null
     * }  $data
     */
    public function handle(PatientCase $case, User $actor, array $data): CaseAppointment
    {
        return DB::transaction(function () use ($case, $actor, $data) {
            /** @var PatientCase $locked */
            $locked = PatientCase::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();

            $departmentId = $data['department_id'] ?? $locked->department_id;
            if ($departmentId !== null) {
                $belongs = Department::query()
                    ->whereKey($departmentId)
                    ->where('team_id', $locked->team_id)
                    ->exists();

                if (! $belongs) {
                    throw new InvalidArgumentException('Selected department does not belong to this organization.');
                }
            }

            $doctorId = $data['doctor_id'] ?? $locked->assigned_doctor_id;
            if ($doctorId !== null) {
                $doctor = User::query()->findOrFail($doctorId);
                if (! $doctor->belongsToTeam($locked->team)) {
                    throw new InvalidArgumentException('Selected doctor must belong to this organization.');
                }
            }

            $appointment = CaseAppointment::query()->create([
                'team_id' => $locked->team_id,
                'patient_case_id' => $locked->id,
                'department_id' => $departmentId,
                'doctor_id' => $doctorId,
                'created_by_user_id' => $actor->id,
                'uuid' => (string) Str::uuid(),
                'status' => AppointmentStatus::Proposed,
                'appointment_type' => AppointmentType::from($data['appointment_type']),
                'proposed_starts_at' => $data['proposed_starts_at'],
                'proposed_ends_at' => $data['proposed_ends_at'] ?? null,
                'location' => $data['location'] ?? null,
                'meeting_instructions' => $data['meeting_instructions'] ?? null,
                'external_his_reference' => $data['external_his_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'hospital_slot_verified' => false,
                'is_demo' => $locked->is_demo,
            ]);

            $locked->forceFill(['last_activity_at' => now()])->save();

            $this->recordActivity->handle(
                case: $locked,
                type: CaseActivityType::AppointmentProposed,
                summary: sprintf('Appointment proposed (%s)', $appointment->appointment_type->label()),
                actor: $actor,
                properties: [
                    'appointment_uuid' => $appointment->uuid,
                    'status' => $appointment->status->value,
                    'proposed_starts_at' => $appointment->proposed_starts_at?->toIso8601String(),
                ],
            );

            return $appointment->fresh(['department', 'doctor:id,name,email', 'createdBy:id,name']);
        });
    }
}
