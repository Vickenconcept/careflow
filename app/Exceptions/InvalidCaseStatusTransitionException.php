<?php

namespace App\Exceptions;

use App\Enums\CaseStatus;
use DomainException;

class InvalidCaseStatusTransitionException extends DomainException
{
    public static function between(CaseStatus $from, CaseStatus $to): self
    {
        return new self(sprintf(
            'Cannot transition case status from [%s] to [%s].',
            $from->value,
            $to->value,
        ));
    }

    public static function missingPrerequisites(CaseStatus $to, string $reason): self
    {
        return new self(sprintf(
            'Cannot transition to [%s]: %s',
            $to->value,
            $reason,
        ));
    }
}
