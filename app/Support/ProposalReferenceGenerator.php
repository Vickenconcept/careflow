<?php

namespace App\Support;

use App\Models\ProposalReferenceSequence;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class ProposalReferenceGenerator
{
    /**
     * Generate the next unique proposal reference for a tenant/year.
     */
    public function next(Team $team, ?int $year = null): string
    {
        $year ??= (int) now()->year;
        $prefix = (string) config('careflow.proposal_reference.prefix', 'TP');
        $padLength = (int) config('careflow.proposal_reference.pad_length', 6);

        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException('Invalid proposal reference year.');
        }

        $number = DB::transaction(function () use ($team, $year): int {
            $this->ensureSequenceExists($team->id, $year);

            $sequence = ProposalReferenceSequence::query()
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
            ProposalReferenceSequence::query()->firstOrCreate(
                [
                    'team_id' => $teamId,
                    'year' => $year,
                ],
                [
                    'last_number' => 0,
                ],
            );
        } catch (Throwable) {
            // Concurrent first inserts can race on the unique index.
        }
    }
}
