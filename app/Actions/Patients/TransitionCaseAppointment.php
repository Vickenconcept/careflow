<?php

namespace App\Actions\Patients;

use App\Domains\Appointments\AppointmentTransitionMap;
use App\Domains\Integrations\His\Contracts\HisSchedulingAdapter;
use App\Enums\AppointmentConfirmationSource;
use App\Enums\AppointmentStatus;
use App\Enums\CaseActivityType;
use App\Exceptions\InvalidAppointmentTransitionException;
use App\Models\CaseAppointment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransitionCaseAppointment
{
    public function __construct(
        private AppointmentTransitionMap $transitions,
        private RecordCaseActivity $recordActivity,
        private HisSchedulingAdapter $hisSchedulingAdapter,
    ) {}

    /**
     * @param  array{
     *     action: string,
     *     confirmation_source?: string|null,
     *     hospital_slot_verified?: bool|null,
     *     confirmed_starts_at?: string|null,
     *     confirmed_ends_at?: string|null,
     *     external_his_reference?: string|null,
     *     proposed_starts_at?: string|null,
     *     proposed_ends_at?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function handle(CaseAppointment $appointment, User $actor, array $data): CaseAppointment
    {
        return DB::transaction(function () use ($appointment, $actor, $data) {
            /** @var CaseAppointment $locked */
            $locked = CaseAppointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            $to = $this->resolveTargetStatus($data['action']);

            if (! $this->transitions->canTransition($locked->status, $to)) {
                throw InvalidAppointmentTransitionException::notAllowed($locked->status, $to);
            }

            $from = $locked->status;

            match ($to) {
                AppointmentStatus::AwaitingConfirmation => $this->markAwaitingConfirmation($locked, $data),
                AppointmentStatus::Confirmed => $this->markConfirmed($locked, $actor, $data),
                AppointmentStatus::Rescheduled => $this->markRescheduled($locked, $data),
                AppointmentStatus::Cancelled => $this->markCancelled($locked, $data),
                AppointmentStatus::Completed => $this->markCompleted($locked, $data),
                default => throw new InvalidArgumentException('Unsupported appointment transition.'),
            };

            $case = $locked->case()->lockForUpdate()->firstOrFail();
            $case->forceFill(['last_activity_at' => now()])->save();

            $this->recordActivity->handle(
                case: $case,
                type: CaseActivityType::AppointmentStatusChanged,
                summary: sprintf('Appointment %s → %s', $from->label(), $to->label()),
                actor: $actor,
                properties: [
                    'appointment_uuid' => $locked->uuid,
                    'from' => $from->value,
                    'to' => $to->value,
                    'hospital_slot_verified' => $locked->hospital_slot_verified,
                    'confirmation_source' => $locked->confirmation_source?->value,
                ],
            );

            return $locked->fresh(['department', 'doctor:id,name,email', 'createdBy:id,name', 'confirmedBy:id,name']);
        });
    }

    protected function resolveTargetStatus(string $action): AppointmentStatus
    {
        return match ($action) {
            'request_confirmation' => AppointmentStatus::AwaitingConfirmation,
            'confirm' => AppointmentStatus::Confirmed,
            'reschedule' => AppointmentStatus::Rescheduled,
            'cancel' => AppointmentStatus::Cancelled,
            'complete' => AppointmentStatus::Completed,
            default => throw new InvalidArgumentException('Unknown appointment action.'),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function markAwaitingConfirmation(CaseAppointment $appointment, array $data): void
    {
        $appointment->forceFill([
            'status' => AppointmentStatus::AwaitingConfirmation,
            'notes' => $data['notes'] ?? $appointment->notes,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function markConfirmed(CaseAppointment $appointment, User $actor, array $data): void
    {
        $verified = (bool) ($data['hospital_slot_verified'] ?? false);
        $sourceValue = $data['confirmation_source'] ?? null;

        if (! $verified || ! is_string($sourceValue) || $sourceValue === '') {
            throw InvalidAppointmentTransitionException::missingConfirmationEvidence();
        }

        $source = AppointmentConfirmationSource::from($sourceValue);
        $externalReference = $data['external_his_reference'] ?? $appointment->external_his_reference;

        if ($source === AppointmentConfirmationSource::ExternalHis) {
            $verifiedHis = $this->hisSchedulingAdapter->verifySlotReference(
                $appointment,
                is_string($externalReference) ? $externalReference : null,
            );
            $externalReference = $verifiedHis['reference'];
        }

        $confirmedStartsAt = $data['confirmed_starts_at']
            ?? $appointment->proposed_starts_at?->toDateTimeString();

        if ($confirmedStartsAt === null) {
            throw new InvalidArgumentException('A confirmed date/time is required.');
        }

        $appointment->forceFill([
            'status' => AppointmentStatus::Confirmed,
            'confirmation_source' => $source,
            'hospital_slot_verified' => true,
            'confirmed_starts_at' => $confirmedStartsAt,
            'confirmed_ends_at' => $data['confirmed_ends_at'] ?? $appointment->proposed_ends_at,
            'external_his_reference' => $externalReference,
            'confirmed_by_user_id' => $actor->id,
            'confirmed_at' => now(),
            'notes' => $data['notes'] ?? $appointment->notes,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function markRescheduled(CaseAppointment $appointment, array $data): void
    {
        if (blank($data['proposed_starts_at'] ?? null)) {
            throw new InvalidArgumentException('A new proposed date/time is required when rescheduling.');
        }

        $appointment->forceFill([
            'status' => AppointmentStatus::Rescheduled,
            'proposed_starts_at' => $data['proposed_starts_at'],
            'proposed_ends_at' => $data['proposed_ends_at'] ?? null,
            'confirmed_starts_at' => null,
            'confirmed_ends_at' => null,
            'confirmation_source' => null,
            'hospital_slot_verified' => false,
            'confirmed_by_user_id' => null,
            'confirmed_at' => null,
            'notes' => $data['notes'] ?? $appointment->notes,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function markCancelled(CaseAppointment $appointment, array $data): void
    {
        $appointment->forceFill([
            'status' => AppointmentStatus::Cancelled,
            'notes' => $data['notes'] ?? $appointment->notes,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function markCompleted(CaseAppointment $appointment, array $data): void
    {
        $appointment->forceFill([
            'status' => AppointmentStatus::Completed,
            'notes' => $data['notes'] ?? $appointment->notes,
        ])->save();
    }
}
