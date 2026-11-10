<?php

namespace App\Queries;

use App\Enums\CaseStatus;
use App\Models\PatientCase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PatientCaseIndexQuery
{
    /**
     * @param  array{
     *     search?: string|null,
     *     status?: string|null,
     *     department_id?: int|null,
     *     assignment?: string|null,
     *     priority?: string|null,
     *     sort?: string|null
     * }  $filters
     * @return LengthAwarePaginator<int, PatientCase>
     */
    public function paginate(User $user, Team $team, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = PatientCase::query()
            ->visibleToStaff($user, $team)
            ->with([
                'patient:id,first_name,last_name,email,country_of_residence,preferred_language',
                'department:id,name',
                'assignedCoordinator:id,name',
                'assignedDoctor:id,name',
            ])
            ->withCount('documents');

        $this->applyFilters($query, $user, $filters);
        $this->applySorting($query, $filters['sort'] ?? 'recent');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  Builder<PatientCase>  $query
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, User $user, array $filters): void
    {
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('patient', function (Builder $patient) use ($search): void {
                        $patient
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['status'])) {
            $status = CaseStatus::tryFrom((string) $filters['status']);

            if ($status !== null) {
                $query->where('status', $status);
            }
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', (int) $filters['department_id']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', (string) $filters['priority']);
        }

        match ($filters['assignment'] ?? null) {
            'mine' => $query->where(function (Builder $builder) use ($user): void {
                $builder
                    ->where('assigned_coordinator_id', $user->id)
                    ->orWhere('assigned_doctor_id', $user->id);
            }),
            'unassigned' => $query->whereNull('assigned_coordinator_id'),
            'unassigned_doctor' => $query->whereNull('assigned_doctor_id'),
            default => null,
        };
    }

    /**
     * @param  Builder<PatientCase>  $query
     */
    protected function applySorting(Builder $query, string $sort): void
    {
        match ($sort) {
            'reference' => $query->orderBy('reference')->orderByDesc('id'),
            'status' => $query->orderBy('status')->orderByDesc('last_activity_at')->orderByDesc('id'),
            'oldest' => $query->orderBy('last_activity_at')->orderBy('id'),
            default => $query->orderByDesc('last_activity_at')->orderByDesc('id'),
        };
    }
}
