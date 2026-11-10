<?php

namespace App\Support;

use App\Models\CaseReferenceSequence;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class CaseReferenceGenerator
{
    /**
     * Generate the next unique case reference for a tenant/year.
     *
     * Uses a locked sequence row so concurrent intakes cannot collide.
     */
    public function next(Team $team, ?int $year = null): string
    {
        $year ??= (int) now()->year;
        $prefix = (string) config('careflow.case_reference.prefix', 'CF');
        $padLength = (int) config('careflow.case_reference.pad_length', 6);

        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException('Invalid case reference year.');
        }

        $number = DB::transaction(function () use ($team, $year): int {
            $this->ensureSequenceExists($team->id, $year);

            $sequence = CaseReferenceSequence::query()
                ->where('team_id', $team->id)
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->last_number++;
            $sequence->save();

            return (int) $sequence->last_number;
        });

        return sprintf(
            '%s-%d-%s',
            $prefix,
            $year,
            str_pad((string) $number, $padLength, '0', STR_PAD_LEFT),
        );
    }

    protected function ensureSequenceExists(int $teamId, int $year): void
    {
        try {
            CaseReferenceSequence::query()->firstOrCreate(
                [
                    'team_id' => $teamId,
                    'year' => $year,
                ],
                [
                    'last_number' => 0,
                ],
            );
        } catch (Throwable) {
            // Concurrent first inserts can race on the unique index; the
            // subsequent lockForUpdate read will observe the winning row.
        }
    }
}
