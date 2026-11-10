<?php

namespace App\Domains\Appointments;

use App\Enums\AppointmentStatus;

class AppointmentTransitionMap
{
    /**
     * @return list<AppointmentStatus>
     */
    public function allowedFrom(AppointmentStatus $from): array
    {
        return match ($from) {
            AppointmentStatus::Proposed => [
                AppointmentStatus::AwaitingConfirmation,
                AppointmentStatus::Cancelled,
            ],
            AppointmentStatus::AwaitingConfirmation => [
                AppointmentStatus::Confirmed,
                AppointmentStatus::Rescheduled,
                AppointmentStatus::Cancelled,
            ],
            AppointmentStatus::Confirmed => [
                AppointmentStatus::Rescheduled,
                AppointmentStatus::Completed,
                AppointmentStatus::Cancelled,
            ],
            AppointmentStatus::Rescheduled => [
                AppointmentStatus::AwaitingConfirmation,
                AppointmentStatus::Confirmed,
                AppointmentStatus::Cancelled,
            ],
            AppointmentStatus::Cancelled,
            AppointmentStatus::Completed => [],
        };
    }

    public function canTransition(AppointmentStatus $from, AppointmentStatus $to): bool
    {
        return in_array($to, $this->allowedFrom($from), true);
    }
}
