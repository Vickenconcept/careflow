<?php

namespace App\Actions\Patients;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\CaseActivityType;
use App\Models\CaseAppointment;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateCaseAppointment
{
    public function __construct(
        private RecordCaseActivity $recordActivity,
    ) {}

    /**
     * @param  array{
     *     department_id?: int|null,
     *     doctor_id?: int|null,
     *     appointment_type?: string,
     *     proposed_starts_at?: string|null,
     *     proposed_ends_at?: string|null,
     *     location?: string|null,
     *     meeting_instructions?: string|null,
     *     notes?: string|null,
     *     external_his_reference?: string|null
     * }  $data
     */
    public function handle(CaseAppointment $appointment, User $actor, array $data): CaseAppointment
    {
        return DB::transaction(function () use ($appointment, $actor, $data) {
            /** @var CaseAppointment $locked */
            $locked = CaseAppointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [AppointmentStatus::Confirmed, AppointmentStatus::Completed, AppointmentStatus::Cancelled], true)) {
                throw new InvalidArgumentException('Confirmed, completed, or cancelled appointments cannot be edited. Transition or reschedule instead.');
            }

            if (array_key_exists('department_id', $data) && $data['department_id'] !== null) {
                $belongs = Department::query()
                    ->whereKey($data['department_id'])
                    ->where('team_id', $locked->team_id)
                    ->exists();

                if (! $belongs) {
                    throw new InvalidArgumentException('Selected department does not belong to this organization.');
                }
            }

            if (array_key_exists('doctor_id', $data) && $data['doctor_id'] !== null) {
                $doctor = User::query()->findOrFail($data['doctor_id']);
                if (! $doctor->belongsToTeam($locked->team)) {
                    throw new InvalidArgumentException('Selected doctor must belong to this organization.');
                }
            }

            $locked->fill([
                'department_id' => array_key_exists('department_id', $data) ? $data['department_id'] : $locked->department_id,
                'doctor_id' => array_key_exists('doctor_id', $data) ? $data['doctor_id'] : $locked->doctor_id,
                'appointment_type' => isset($data['appointment_type'])
                    ? AppointmentType::from($data['appointment_type'])
                    : $locked->appointment_type,
                'proposed_starts_at' => array_key_exists('proposed_starts_at', $data) ? $data['proposed_starts_at'] : $locked->proposed_starts_at,
                'proposed_ends_at' => array_key_exists('proposed_ends_at', $data) ? $data['proposed_ends_at'] : $locked->proposed_ends_at,
                'location' => array_key_exists('location', $data) ? $data['location'] : $locked->location,
                'meeting_instructions' => array_key_exists('meeting_instructions', $data) ? $data['meeting_instructions'] : $locked->meeting_instructions,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $locked->notes,
                'external_his_reference' => array_key_exists('external_his_reference', $data) ? $data['external_his_reference'] : $locked->external_his_reference,
            ])->save();

            $case = $locked->case()->lockForUpdate()->firstOrFail();
            $case->forceFill(['last_activity_at' => now()])->save();

            $this->recordActivity->handle(
                case: $case,
                type: CaseActivityType::AppointmentUpdated,
                summary: 'Appointment details updated',
                actor: $actor,
                properties: [
                    'appointment_uuid' => $locked->uuid,
                    'status' => $locked->status->value,
                ],
            );

            return $locked->fresh(['department', 'doctor:id,name,email', 'createdBy:id,name']);
        });
    }
}
