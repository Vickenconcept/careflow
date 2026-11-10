<?php

namespace App\Domains\Integrations\His\Contracts;

use App\Models\CaseAppointment;

interface HisSchedulingAdapter
{
    /**
     * Validate / normalize an external HIS scheduling reference before confirmation.
     *
     * @return array{provider: string, reference: string, verified: bool}
     */
    public function verifySlotReference(CaseAppointment $appointment, ?string $externalReference): array;
}
