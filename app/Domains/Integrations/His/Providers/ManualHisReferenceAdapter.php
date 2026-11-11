<?php

namespace App\Domains\Integrations\His\Providers;

use App\Domains\Integrations\His\Contracts\HisSchedulingAdapter;
use App\Models\CaseAppointment;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Used while no hospital information system is connected. The booking reference
 * is typed by staff from the hospital's own system; CareFlow records it as entered
 * and never claims it was verified electronically.
 */
class ManualHisReferenceAdapter implements HisSchedulingAdapter
{
    public function verifySlotReference(CaseAppointment $appointment, ?string $externalReference): array
    {
        $reference = trim((string) $externalReference);

        if ($reference === '') {
            throw new InvalidArgumentException('Enter the booking reference from the hospital system.');
        }

        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]{2,63}$/', $reference)) {
            throw new InvalidArgumentException('The booking reference may only contain letters, numbers, dots, dashes and slashes.');
        }

        return [
            'provider' => 'manual_entry',
            'reference' => Str::upper($reference),
            'verified' => false,
        ];
    }
}
