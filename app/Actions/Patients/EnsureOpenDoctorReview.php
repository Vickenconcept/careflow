<?php

namespace App\Actions\Patients;

use App\Enums\DoctorReviewStatus;
use App\Models\DoctorReview;
use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EnsureOpenDoctorReview
{
    public function handle(PatientCase $case, User $reviewer): DoctorReview
    {
        if ($case->assigned_doctor_id !== null && $case->assigned_doctor_id !== $reviewer->id) {
            $role = $reviewer->teamRole($case->team);

            // Owners/admins with clinical review permission may assist, but
            // assigned doctors own the open review thread for the case.
            if ($role?->value === 'doctor') {
                throw new InvalidArgumentException('Only the assigned doctor can open a clinical review for this case.');
            }
        }

        return DB::transaction(function () use ($case, $reviewer) {
            $existing = DoctorReview::query()
                ->where('patient_case_id', $case->id)
                ->where('reviewer_user_id', $reviewer->id)
                ->whereIn('status', [
                    DoctorReviewStatus::Draft->value,
                    DoctorReviewStatus::InProgress->value,
                ])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existing !== null) {
                if ($existing->status === DoctorReviewStatus::Draft) {
                    $existing->update(['status' => DoctorReviewStatus::InProgress]);
                }

                return $existing->fresh();
            }

            return DoctorReview::query()->create([
                'team_id' => $case->team_id,
                'patient_case_id' => $case->id,
                'reviewer_user_id' => $reviewer->id,
                'uuid' => (string) Str::uuid(),
                'status' => DoctorReviewStatus::InProgress,
                'started_at' => now(),
                'is_demo' => $case->is_demo,
            ]);
        });
    }
}
