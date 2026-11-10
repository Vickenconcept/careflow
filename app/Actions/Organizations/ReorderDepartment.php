<?php

namespace App\Actions\Organizations;

use App\Models\Department;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReorderDepartment
{
    /**
     * Move a department one position up or down within its team list.
     *
     * @param  'up'|'down'  $direction
     */
    public function handle(Team $team, Department $department, string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Direction must be up or down.');
        }

        DB::transaction(function () use ($team, $department, $direction): void {
            $ordered = $team->departments()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $index = $ordered->search(fn (Department $item): bool => $item->id === $department->id);

            if ($index === false) {
                return;
            }

            $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;

            if ($targetIndex < 0 || $targetIndex >= $ordered->count()) {
                return;
            }

            $ids = $ordered->pluck('id')->all();
            $movingId = $ids[$index];
            unset($ids[$index]);
            $ids = array_values($ids);
            array_splice($ids, $targetIndex, 0, [$movingId]);

            foreach ($ids as $position => $id) {
                Department::query()
                    ->whereKey($id)
                    ->where('team_id', $team->id)
                    ->update(['sort_order' => $position]);
            }
        });
    }
}
