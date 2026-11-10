<?php

namespace App\Queries;

use App\Enums\AppointmentStatus;
use App\Enums\CaseStatus;
use App\Enums\CaseTaskStatus;
use App\Enums\DocumentProcessingStatus;
use App\Enums\FollowUpStatus;
use App\Enums\TeamPermission;
use App\Enums\TreatmentProposalStatus;
use App\Models\CaseActivity;
use App\Models\CaseAppointment;
use App\Models\CaseTask;
use App\Models\DoctorReview;
use App\Models\DocumentProcessingRun;
use App\Models\PatientCase;
use App\Models\PatientDocument;
use App\Models\PatientEnquiry;
use App\Models\PatientFollowUp;
use App\Models\Team;
use App\Models\TreatmentProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class OrganizationDashboardMetricsQuery
{
    /**
     * @param  array{
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     status?: string|null,
     *     department_id?: int|null,
     *     country?: string|null,
     *     language?: string|null,
     *     coordinator_id?: int|null,
     *     doctor_id?: int|null
     * }  $filters
     * @return array<string, mixed>
     */
    public function for(User $user, ?Team $team, array $filters = []): array
    {
        $empty = [
            'available' => false,
            'definitions' => [],
            'executive' => [],
            'operational' => [],
            'open_cases' => 0,
            'awaiting_coordinator_review' => 0,
            'awaiting_doctor_review' => 0,
            'documents_needing_review' => 0,
            'demo_cases' => 0,
            'recent_cases' => [],
            'workload_by_coordinator' => [],
            'filters' => $filters,
        ];

        if ($team === null || ! $user->belongsToTeam($team)) {
            return $empty;
        }

        $canViewCases = $user->hasTeamPermission($team, TeamPermission::ViewCases);
        $canViewReports = $user->hasTeamPermission($team, TeamPermission::ViewReports);

        if (! $canViewCases && ! $canViewReports) {
            return $empty;
        }

        // Case staff see their visible cases; report-only roles see org aggregates without case lists.
        $cases = fn (): Builder => $this->applyFilters(
            $canViewCases
                ? PatientCase::query()->visibleToStaff($user, $team)
                : PatientCase::query()->forTeam($team),
            $filters,
        );
        $includeCaseList = $canViewCases;

        $openStatuses = array_values(array_filter(
            CaseStatus::cases(),
            fn (CaseStatus $status) => ! in_array($status, [CaseStatus::Closed, CaseStatus::Declined], true),
        ));

        $recent = $includeCaseList
            ? $cases()
                ->with('patient')
                ->orderByDesc('last_activity_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                ->map(fn (PatientCase $case) => [
                    'reference' => $case->reference,
                    'status_label' => $case->status->label(),
                    'patient_name' => $case->patient->fullName(),
                    'last_activity_at' => $case->last_activity_at?->toIso8601String(),
                ])
                ->all()
            : [];

        $visibleCaseIds = $cases()->pluck('id');

        $documentsNeedingReview = PatientDocument::query()
            ->where('team_id', $team->id)
            ->where('processing_status', DocumentProcessingStatus::NeedsReview)
            ->whereIn('patient_case_id', $visibleCaseIds)
            ->count();

        $totalEnquiries = PatientEnquiry::query()
            ->where('team_id', $team->id)
            ->when($filters['date_from'] ?? null, fn ($query, $from) => $query->whereDate('submitted_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($query, $to) => $query->whereDate('submitted_at', '<=', $to))
            ->when($filters['country'] ?? null, fn ($query, $country) => $query->whereHas(
                'patient',
                fn ($patient) => $patient->where('country_of_residence', $country),
            ))
            ->when($filters['language'] ?? null, fn ($query, $language) => $query->whereHas(
                'patient',
                fn ($patient) => $patient->where('preferred_language', $language),
            ))
            ->count();
        $newEnquiries = $cases()->where('status', CaseStatus::NewEnquiry)->count();
        $openCases = $cases()->whereIn('status', $openStatuses)->count();
        $awaitingDocuments = $cases()->whereIn('status', [
            CaseStatus::AwaitingDocuments,
            CaseStatus::AdditionalInformationRequired,
        ])->count();
        $awaitingDoctorReview = $cases()->where('status', CaseStatus::AwaitingDoctorReview)->count();
        $awaitingPatientResponse = $cases()->where('status', CaseStatus::AwaitingPatientResponse)->count();
        $awaitingCoordinatorReview = $cases()->where('status', CaseStatus::AwaitingCoordinatorReview)->count();

        $proposalsSent = TreatmentProposal::query()
            ->where('team_id', $team->id)
            ->whereIn('patient_case_id', $visibleCaseIds)
            ->whereIn('status', [
                TreatmentProposalStatus::SentToPatient->value,
                TreatmentProposalStatus::PatientAccepted->value,
                TreatmentProposalStatus::PatientDeclined->value,
            ])
            ->count();

        $proposalsAccepted = TreatmentProposal::query()
            ->where('team_id', $team->id)
            ->whereIn('patient_case_id', $visibleCaseIds)
            ->where('status', TreatmentProposalStatus::PatientAccepted->value)
            ->count();

        $appointmentsConfirmed = CaseAppointment::query()
            ->where('team_id', $team->id)
            ->whereIn('patient_case_id', $visibleCaseIds)
            ->where('status', AppointmentStatus::Confirmed->value)
            ->where('hospital_slot_verified', true)
            ->count();

        $appointmentsCompleted = CaseAppointment::query()
            ->where('team_id', $team->id)
            ->whereIn('patient_case_id', $visibleCaseIds)
            ->where('status', AppointmentStatus::Completed->value)
            ->count();

        $treatmentsCompleted = $cases()->whereIn('status', [
            CaseStatus::TreatmentCompleted,
            CaseStatus::FollowUp,
            CaseStatus::Closed,
        ])->count();

        $overdueFollowUps = PatientFollowUp::query()
            ->where('team_id', $team->id)
            ->whereIn('patient_case_id', $visibleCaseIds)
            ->whereNotIn('status', [FollowUpStatus::Completed->value, FollowUpStatus::Cancelled->value])
            ->where('due_at', '<', now())
            ->count();

        $outstandingTasks = CaseTask::query()
            ->where('team_id', $team->id)
            ->whereIn('patient_case_id', $visibleCaseIds)
            ->whereNotIn('status', [CaseTaskStatus::Completed->value, CaseTaskStatus::Cancelled->value])
            ->count();

        $enquiryToProposalConversion = $totalEnquiries > 0
            ? round(($proposalsSent / $totalEnquiries) * 100, 1)
            : 0.0;

        $proposalToAcceptanceConversion = $proposalsSent > 0
            ? round(($proposalsAccepted / $proposalsSent) * 100, 1)
            : 0.0;

        $appointmentDenom = $appointmentsConfirmed + $appointmentsCompleted;
        $appointmentCompletionRate = $appointmentDenom > 0
            ? round(($appointmentsCompleted / $appointmentDenom) * 100, 1)
            : 0.0;

        $workload = User::query()
            ->whereIn('id', $cases()
                ->whereNotNull('assigned_coordinator_id')
                ->pluck('assigned_coordinator_id')
                ->unique()
                ->filter()
                ->all())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $coordinator) => [
                'coordinator' => $coordinator->name,
                'open_cases' => $cases()
                    ->where('assigned_coordinator_id', $coordinator->id)
                    ->whereIn('status', $openStatuses)
                    ->count(),
            ])
            ->filter(fn (array $row) => $row['open_cases'] > 0)
            ->values()
            ->all();

        return [
            'available' => true,
            'definitions' => [
                'open_cases' => 'Cases not Closed or Declined.',
                'enquiry_to_proposal_conversion' => 'Proposals that reached Sent/Accepted/Declined divided by total enquiries.',
                'proposal_to_acceptance_conversion' => 'Patient-accepted proposals divided by proposals sent.',
                'overdue_follow_ups' => 'Follow-ups due in the past that are not completed or cancelled.',
                'avg_first_response_hours' => 'Hours from enquiry submit to first staff case activity.',
                'avg_document_processing_hours' => 'Hours from document upload to processing run finish.',
                'avg_doctor_review_hours' => 'Hours from doctor review start to completion.',
                'avg_proposal_turnaround_hours' => 'Hours from proposal create to sent_at.',
            ],
            'executive' => [
                'total_enquiries' => $totalEnquiries,
                'new_enquiries' => $newEnquiries,
                'active_cases' => $openCases,
                'awaiting_documents' => $awaitingDocuments,
                'awaiting_doctor_review' => $awaitingDoctorReview,
                'awaiting_patient_response' => $awaitingPatientResponse,
                'proposals_sent' => $proposalsSent,
                'proposals_accepted' => $proposalsAccepted,
                'appointments_confirmed' => $appointmentsConfirmed,
                'treatments_completed' => $treatmentsCompleted,
                'overdue_follow_ups' => $overdueFollowUps,
            ],
            'operational' => [
                'avg_first_response_hours' => $this->averageFirstResponseHours($team, $visibleCaseIds->all()),
                'avg_document_processing_hours' => $this->averageDocumentProcessingHours($team, $visibleCaseIds->all()),
                'avg_doctor_review_hours' => $this->averageDoctorReviewHours($team, $visibleCaseIds->all()),
                'avg_proposal_turnaround_hours' => $this->averageProposalTurnaroundHours($team, $visibleCaseIds->all()),
                'enquiry_to_proposal_conversion' => $enquiryToProposalConversion,
                'proposal_to_acceptance_conversion' => $proposalToAcceptanceConversion,
                'appointment_completion_rate' => $appointmentCompletionRate,
                'outstanding_tasks' => $outstandingTasks,
            ],
            'open_cases' => $openCases,
            'awaiting_coordinator_review' => $awaitingCoordinatorReview,
            'awaiting_doctor_review' => $awaitingDoctorReview,
            'documents_needing_review' => $documentsNeedingReview,
            'demo_cases' => $cases()->where('is_demo', true)->count(),
            'recent_cases' => $recent,
            'workload_by_coordinator' => $workload,
            'filters' => $filters,
        ];
    }

    /**
     * @param  array{
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     status?: string|null,
     *     department_id?: int|null,
     *     country?: string|null,
     *     language?: string|null,
     *     coordinator_id?: int|null,
     *     doctor_id?: int|null
     * }  $filters
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['date_from'] ?? null, fn (Builder $builder, string $from) => $builder->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn (Builder $builder, string $to) => $builder->whereDate('created_at', '<=', $to))
            ->when($filters['status'] ?? null, fn (Builder $builder, string $status) => $builder->where('status', $status))
            ->when($filters['department_id'] ?? null, fn (Builder $builder, int $departmentId) => $builder->where('department_id', $departmentId))
            ->when($filters['coordinator_id'] ?? null, fn (Builder $builder, int $coordinatorId) => $builder->where('assigned_coordinator_id', $coordinatorId))
            ->when($filters['doctor_id'] ?? null, fn (Builder $builder, int $doctorId) => $builder->where('assigned_doctor_id', $doctorId))
            ->when($filters['country'] ?? null, fn (Builder $builder, string $country) => $builder->whereHas(
                'patient',
                fn ($patient) => $patient->where('country_of_residence', $country),
            ))
            ->when($filters['language'] ?? null, fn (Builder $builder, string $language) => $builder->whereHas(
                'patient',
                fn ($patient) => $patient->where('preferred_language', $language),
            ));
    }

    /**
     * @param  list<int>  $caseIds
     */
    protected function averageFirstResponseHours(Team $team, array $caseIds): ?float
    {
        if ($caseIds === []) {
            return null;
        }

        $hours = PatientCase::query()
            ->with(['enquiry', 'activities' => fn ($query) => $query
                ->whereNotNull('actor_user_id')
                ->orderBy('occurred_at')
                ->orderBy('id')])
            ->where('team_id', $team->id)
            ->whereIn('id', $caseIds)
            ->get()
            ->map(function (PatientCase $case) {
                $submittedAt = $case->enquiry?->submitted_at;
                /** @var CaseActivity|null $firstStaffActivity */
                $firstStaffActivity = $case->activities->first();

                if ($submittedAt === null || $firstStaffActivity?->occurred_at === null) {
                    return null;
                }

                return $submittedAt->diffInHours($firstStaffActivity->occurred_at, absolute: true);
            })
            ->filter(fn ($value) => is_numeric($value))
            ->values();

        return $hours->isEmpty() ? null : round((float) $hours->avg(), 1);
    }

    /**
     * @param  list<int>  $caseIds
     */
    protected function averageDocumentProcessingHours(Team $team, array $caseIds): ?float
    {
        if ($caseIds === []) {
            return null;
        }

        $hours = DocumentProcessingRun::query()
            ->with('document')
            ->where('team_id', $team->id)
            ->whereNotNull('finished_at')
            ->whereHas('document', fn ($query) => $query->whereIn('patient_case_id', $caseIds))
            ->get()
            ->map(function (DocumentProcessingRun $run) {
                $uploadedAt = $run->document?->uploaded_at;
                $finishedAt = $run->finished_at;

                if (! $uploadedAt instanceof Carbon || ! $finishedAt instanceof Carbon) {
                    return null;
                }

                return $uploadedAt->diffInHours($finishedAt, absolute: true);
            })
            ->filter(fn ($value) => is_numeric($value))
            ->values();

        return $hours->isEmpty() ? null : round((float) $hours->avg(), 1);
    }

    /**
     * @param  list<int>  $caseIds
     */
    protected function averageDoctorReviewHours(Team $team, array $caseIds): ?float
    {
        if ($caseIds === []) {
            return null;
        }

        $hours = DoctorReview::query()
            ->where('team_id', $team->id)
            ->whereIn('patient_case_id', $caseIds)
            ->whereNotNull('completed_at')
            ->whereNotNull('started_at')
            ->get()
            ->map(fn (DoctorReview $review) => $review->started_at->diffInHours($review->completed_at, absolute: true))
            ->filter(fn ($value) => is_numeric($value))
            ->values();

        return $hours->isEmpty() ? null : round((float) $hours->avg(), 1);
    }

    /**
     * @param  list<int>  $caseIds
     */
    protected function averageProposalTurnaroundHours(Team $team, array $caseIds): ?float
    {
        if ($caseIds === []) {
            return null;
        }

        $hours = TreatmentProposal::query()
            ->where('team_id', $team->id)
            ->whereIn('patient_case_id', $caseIds)
            ->whereNotNull('sent_at')
            ->get()
            ->map(fn (TreatmentProposal $proposal) => $proposal->created_at->diffInHours($proposal->sent_at, absolute: true))
            ->filter(fn ($value) => is_numeric($value))
            ->values();

        return $hours->isEmpty() ? null : round((float) $hours->avg(), 1);
    }
}
