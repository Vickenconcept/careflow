<?php

namespace App\Http\Controllers\Organizations;

use App\Enums\AppointmentStatus;
use App\Enums\CaseStatus;
use App\Enums\DoctorReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\CaseAppointment;
use App\Models\DoctorReview;
use App\Models\PatientCase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DoctorQueueController extends Controller
{
    public function __invoke(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [PatientCase::class, $current_team]);

        $user = $request->user();

        $queue = PatientCase::query()
            ->visibleToStaff($user, $current_team)
            ->with(['patient', 'department:id,name', 'assignedCoordinator:id,name', 'assignedDoctor:id,name'])
            ->whereIn('status', [
                CaseStatus::AwaitingDoctorReview,
                CaseStatus::AdditionalInformationRequired,
                CaseStatus::TreatmentProposalPreparation,
            ])
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (PatientCase $case) => [
                'reference' => $case->reference,
                'status' => $case->status->value,
                'status_label' => $case->status->label(),
                'priority' => $case->priority->value,
                'patient_name' => $case->patient->fullName(),
                'department' => $case->department?->name,
                'assigned_coordinator' => $case->assignedCoordinator?->name,
                'assigned_doctor' => $case->assignedDoctor?->name,
                'assigned_to_me' => $case->assigned_doctor_id === $user->id,
                'last_activity_at' => $case->last_activity_at?->toIso8601String(),
                'is_demo' => $case->is_demo,
            ]);

        return Inertia::render('organizations/doctor-queue', [
            'team' => [
                'id' => $current_team->id,
                'name' => $current_team->name,
                'slug' => $current_team->slug,
            ],
            'queue' => $queue,
            'stats' => $this->reviewerStats($user, $current_team, $queue->where('status', CaseStatus::AwaitingDoctorReview->value)->count()),
            'recentlyReviewed' => $this->recentlyReviewed($user, $current_team),
            'upcoming' => $this->upcomingConsultations($user, $current_team),
        ]);
    }

    /**
     * @return array{awaiting_review: int, average_review_hours: float|null, reviews_last_90_days: int}
     */
    protected function reviewerStats(User $user, Team $team, int $awaitingReview): array
    {
        $reviews = DoctorReview::query()
            ->forTeam($team)
            ->where('reviewer_user_id', $user->id)
            ->where('status', DoctorReviewStatus::Completed)
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subDays(90))
            ->get(['started_at', 'completed_at']);

        $average = $reviews->isEmpty()
            ? null
            : round($reviews->avg(fn (DoctorReview $review) => $review->started_at->diffInMinutes($review->completed_at)) / 60, 1);

        return [
            'awaiting_review' => $awaitingReview,
            'average_review_hours' => $average,
            'reviews_last_90_days' => $reviews->count(),
        ];
    }

    /**
     * @return list<array{reference: string, patient_name: string|null, outcome: string|null, completed_at: string|null, url: string}>
     */
    protected function recentlyReviewed(User $user, Team $team): array
    {
        return DoctorReview::query()
            ->forTeam($team)
            ->where('reviewer_user_id', $user->id)
            ->where('status', DoctorReviewStatus::Completed)
            ->whereIn('patient_case_id', PatientCase::query()->visibleToStaff($user, $team)->select('patient_cases.id'))
            ->with('case.patient')
            ->latest('completed_at')
            ->limit(5)
            ->get()
            ->map(fn (DoctorReview $review) => [
                'reference' => $review->case->reference,
                'patient_name' => $review->case->patient?->fullName(),
                'outcome' => $review->outcome?->label(),
                'completed_at' => $review->completed_at?->toIso8601String(),
                'url' => route('organizations.cases.show', ['current_team' => $team->slug, 'case' => $review->case->reference]).'?tab=evaluation',
            ])
            ->values()
            ->all();
    }

    /**
     * Only hospital-verified confirmed consultations booked with this doctor.
     *
     * @return list<array{reference: string, patient_name: string|null, type: string, starts_at: string, location: string|null, url: string}>
     */
    protected function upcomingConsultations(User $user, Team $team): array
    {
        if (! $team->moduleEnabled('appointments')) {
            return [];
        }

        return CaseAppointment::query()
            ->forTeam($team)
            ->where('doctor_id', $user->id)
            ->where('status', AppointmentStatus::Confirmed)
            ->where('hospital_slot_verified', true)
            ->where('confirmed_starts_at', '>=', now())
            ->whereIn('patient_case_id', PatientCase::query()->visibleToStaff($user, $team)->select('patient_cases.id'))
            ->with('case.patient')
            ->orderBy('confirmed_starts_at')
            ->limit(5)
            ->get()
            ->map(fn (CaseAppointment $appointment) => [
                'reference' => $appointment->case->reference,
                'patient_name' => $appointment->case->patient?->fullName(),
                'type' => $appointment->appointment_type->label(),
                'starts_at' => $appointment->confirmed_starts_at->toIso8601String(),
                'location' => $appointment->location,
                'url' => route('organizations.cases.show', ['current_team' => $team->slug, 'case' => $appointment->case->reference]),
            ])
            ->values()
            ->all();
    }
}
