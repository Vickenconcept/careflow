<?php

namespace App\Exceptions;

use App\Enums\AppointmentStatus;
use RuntimeException;

class InvalidAppointmentTransitionException extends RuntimeException
{
    public static function notAllowed(AppointmentStatus $from, AppointmentStatus $to): self
    {
        return new self(sprintf(
            'Cannot transition appointment from %s to %s.',
            $from->label(),
            $to->label(),
        ));
    }

    public static function missingConfirmationEvidence(): self
    {
        return new self(
            'Appointments can only be marked confirmed after hospital/HIS verification. Provide a confirmation source and mark the hospital slot as verified.',
        );
    }
}
