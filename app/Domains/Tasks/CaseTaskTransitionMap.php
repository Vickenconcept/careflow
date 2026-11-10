<?php

namespace App\Domains\Tasks;

use App\Enums\CaseTaskStatus;

class CaseTaskTransitionMap
{
    /**
     * @return list<CaseTaskStatus>
     */
    public function allowedFrom(CaseTaskStatus $from): array
    {
        return match ($from) {
            CaseTaskStatus::Open => [
                CaseTaskStatus::InProgress,
                CaseTaskStatus::Waiting,
                CaseTaskStatus::Completed,
                CaseTaskStatus::Cancelled,
            ],
            CaseTaskStatus::InProgress => [
                CaseTaskStatus::Waiting,
                CaseTaskStatus::Completed,
                CaseTaskStatus::Cancelled,
                CaseTaskStatus::Open,
            ],
            CaseTaskStatus::Waiting => [
                CaseTaskStatus::InProgress,
                CaseTaskStatus::Completed,
                CaseTaskStatus::Cancelled,
                CaseTaskStatus::Open,
            ],
            CaseTaskStatus::Completed,
            CaseTaskStatus::Cancelled => [],
        };
    }

    public function canTransition(CaseTaskStatus $from, CaseTaskStatus $to): bool
    {
        return in_array($to, $this->allowedFrom($from), true);
    }
}
