<?php

namespace App\Domains\Integrations\His\Providers;

use App\Domains\Integrations\His\Contracts\HisSchedulingAdapter;
use App\Models\CaseAppointment;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MockHisSchedulingAdapter implements HisSchedulingAdapter
{
    public function verifySlotReference(CaseAppointment $appointment, ?string $externalReference): array
    {
        $reference = trim((string) $externalReference);

        if ($reference === '') {
            throw new InvalidArgumentException('External HIS confirmation requires an HIS reference.');
        }

        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,63}$/', $reference)) {
            throw new InvalidArgumentException('HIS reference format is invalid for the mock adapter.');
        }

        return [
            'provider' => 'mock_his',
            'reference' => Str::upper($reference),
            'verified' => true,
        ];
    }
}
