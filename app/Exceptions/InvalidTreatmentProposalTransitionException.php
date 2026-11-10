<?php

namespace App\Exceptions;

use App\Enums\TreatmentProposalStatus;
use DomainException;

class InvalidTreatmentProposalTransitionException extends DomainException
{
    public static function between(TreatmentProposalStatus $from, TreatmentProposalStatus $to): self
    {
        return new self(sprintf(
            'Cannot transition treatment proposal from [%s] to [%s].',
            $from->value,
            $to->value,
        ));
    }

    public static function missingPrerequisite(string $reason): self
    {
        return new self($reason);
    }
}
