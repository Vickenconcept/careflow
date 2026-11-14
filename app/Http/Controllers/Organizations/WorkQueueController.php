<?php

namespace App\Http\Controllers\Organizations;

use App\Enums\AppointmentStatus;
use App\Enums\CaseTaskStatus;
use App\Enums\ClinicalRecommendationStatus;
use App\Enums\CommunicationDirection;
use App\Enums\CommunicationStatus;
use App\Enums\FollowUpStatus;
use App\Enums\TeamPermission;
use App\Enums\TreatmentProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\CaseAppointment;
use App\Models\CaseCommunication;
use App\Models\CaseTask;
use App\Models\ClinicalRecommendation;
use App\Models\PatientCase;
use App\Models\PatientDocument;
use App\Models\PatientFollowUp;
use App\Models\Team;
use App\Models\TreatmentProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkQueueController extends Controller
{
    /**
     * One place for everything that needs the signed-in staff member's attention,
     * across every case they are allowed to open.
     */
    public function __invoke(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [PatientCase::class, $current_team]);

        $user = $request->user();
        $queries = self::attentionQueries($user, $current_team);

        $caseSummary = fn ($case) => [
            'reference' => $case->reference,
            'patient_name' => $case->patient?->fullName(),
            'url' => route('organizations.cases.show', ['current_team' => $current_team->slug, 'case' => $case->reference]),
        ];

        $canManageTasks = $user->hasTeamPermission($current_team, TeamPermission::ManageTasks);

        $tasks = $queries['tasks']
            ->with('case.patient')
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->limit(50)
            ->get()
            ->map(fn (CaseTask $task) => [
                'id' => $task->uuid,
                'title' => $task->title,
                'status_label' => $task->status->label(),
                'priority' => $task->priority->value,
                'due_at' => $task->due_at?->toIso8601String(),
                'overdue' => $task->due_at !== null && $task->due_at->isPast(),
                'case' => $caseSummary($task->case),
                'tab' => 'tasks',
                'complete_url' => $canManageTasks ? route('organizations.work.tasks.complete', [
                    'current_team' => $current_team->slug,
                    'case' => $task->case->reference,
                    'task' => $task->uuid,
                ]) : null,
            ]);

        $followUps = $queries['follow_ups']
            ->with('case.patient')
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->limit(50)
            ->get()
            ->map(fn (PatientFollowUp $followUp) => [
                'id' => $followUp->uuid,
                'title' => $followUp->title,
                'status_label' => $followUp->status->label(),
                'due_at' => $followUp->due_at?->toIso8601String(),
                'overdue' => $followUp->due_at !== null && $followUp->due_at->isPast(),
                'case' => $caseSummary($followUp->case),
                'tab' => 'tasks',
                'complete_url' => $canManageTasks ? route('organizations.work.follow-ups.complete', [
                    'current_team' => $current_team->slug,
                    'case' => $followUp->case->reference,
                    'followUp' => $followUp->uuid,
                ]) : null,
            ]);

        $appointments = $queries['appointments'] === null ? collect() : $queries['appointments']
            ->with('case.patient')
            ->orderBy('proposed_starts_at')
            ->limit(30)
            ->get()
            ->map(fn (CaseAppointment $appointment) => [
                'id' => $appointment->uuid,
                'title' => $appointment->appointment_type->label(),
                'status_label' => $appointment->status->label(),
                'due_at' => $appointment->proposed_starts_at?->toIso8601String(),
                'overdue' => false,
                'case' => $caseSummary($appointment->case),
                'tab' => 'appointments',
            ]);

        $messages = $queries['messages'] === null ? collect() : $queries['messages']
            ->with('case.patient')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (CaseCommunication $communication) => [
                'id' => $communication->uuid,
                'title' => $communication->subject ?: str($communication->body)->limit(80)->toString(),
                'status_label' => $communication->channel->label().' · '.$communication->status->label(),
                'due_at' => $communication->created_at?->toIso8601String(),
                'overdue' => false,
                'case' => $caseSummary($communication->case),
                'tab' => 'communications',
            ]);

        $replies = collect();

        if ($queries['replies'] !== null) {
            $waitingCases = $queries['replies']
                ->addSelect(['latest_message_id' => self::latestPatientFacingMessage('id')])
                ->with('patient')
                ->orderBy('last_activity_at')
                ->limit(30)
                ->get();

            $latestMessages = CaseCommunication::query()
                ->whereIn('id', $waitingCases->pluck('latest_message_id')->filter())
                ->get()
                ->keyBy('id');

            $replies = $waitingCases
                ->map(function (PatientCase $case) use ($latestMessages, $caseSummary) {
                    $message = $latestMessages->get($case->latest_message_id);

                    return [
                        'id' => 'reply-'.$case->reference,
                        'title' => $message ? str($message->body)->squish()->limit(100)->toString() : 'New message from the patient',
                        'status_label' => $message ? $message->channel->label() : 'Message',
                        'due_at' => $message?->created_at?->toIso8601String(),
                        'overdue' => $message?->created_at !== null && $message->created_at->lt(now()->subDay()),
                        'case' => $caseSummary($case),
                        'tab' => 'communications',
                    ];
                })
                ->sortBy('due_at')
                ->values();
        }

        $documents = $queries['documents'] === null ? collect() : $queries['documents']
            ->with('case.patient')
            ->orderBy('uploaded_at')
            ->limit(30)
            ->get()
            ->map(fn (PatientDocument $document) => [
                'id' => $document->uuid,
                'title' => $document->original_filename,
                'status_label' => $document->category?->label() ?? 'Document',
                'due_at' => $document->uploaded_at?->toIso8601String(),
                'overdue' => false,
                'case' => $caseSummary($document->case),
                'tab' => 'documents',
                'url' => route('organizations.documents.show', [
                    'current_team' => $current_team->slug,
                    'case' => $document->case->reference,
                    'document' => $document->uuid,
                ]),
            ]);

        $proposalApprovals = $queries['proposal_approvals'] === null ? collect() : $queries['proposal_approvals']
            ->with('case.patient')
            ->orderBy('submitted_for_approval_at')
            ->limit(30)
            ->get()
            ->map(fn (TreatmentProposal $proposal) => [
                'id' => $proposal->uuid,
                'title' => 'Treatment proposal: '.str($proposal->proposed_procedure ?: $proposal->reference)->limit(80)->toString(),
                'status_label' => $proposal->status->label(),
                'due_at' => ($proposal->submitted_for_approval_at ?? $proposal->updated_at)?->toIso8601String(),
                'overdue' => false,
                'case' => $caseSummary($proposal->case),
                'tab' => 'proposal',
            ]);

        $recommendationApprovals = $queries['recommendation_approvals'] === null ? collect() : $queries['recommendation_approvals']
            ->with('case.patient')
            ->orderBy('updated_at')
            ->limit(30)
            ->get()
            ->map(fn (ClinicalRecommendation $recommendation) => [
                'id' => $recommendation->uuid,
                'title' => 'Clinical recommendation: '.str($recommendation->recommendation_text)->squish()->limit(80)->toString(),
                'status_label' => $recommendation->status->label(),
                'due_at' => $recommendation->updated_at?->toIso8601String(),
                'overdue' => false,
                'case' => $caseSummary($recommendation->case),
                'tab' => 'evaluation',
            ]);

        return Inertia::render('organizations/work', [
            'team' => [
                'id' => $current_team->id,
                'name' => $current_team->name,
                'slug' => $current_team->slug,
            ],
            'sections' => [
                'tasks' => $tasks->values(),
                'follow_ups' => $followUps->values(),
                'appointments' => $appointments->values(),
                'messages' => $messages->values(),
                'replies' => $replies->values(),
                'documents' => $documents->values(),
                'approvals' => $proposalApprovals->concat($recommendationApprovals)->values(),
            ],
            'canApproveMessages' => $queries['messages'] !== null,
            'canSeeReplies' => $queries['replies'] !== null,
            'canReviewDocuments' => $queries['documents'] !== null,
            'canApproveClinical' => $queries['proposal_approvals'] !== null || $queries['recommendation_approvals'] !== null,
            'attentionCount' => self::attentionCount($user, $current_team),
        ]);
    }

    /**
     * How many things need this staff member's attention (the number on the header bell).
     */
    public static function attentionCount(User $user, Team $team): int
    {
        if (! $user->hasTeamPermission($team, TeamPermission::ViewCases)) {
            return 0;
        }

        $count = fn (): int => (int) collect(self::attentionQueries($user, $team))
            ->filter()
            ->sum(fn (Builder $query) => $query->count());

        // The bell and the My work page both ask for this on the same request.
        if (app()->runningInConsole() || ! app()->bound('request')) {
            return $count();
        }

        $key = 'careflow.attention_count.'.$user->getKey().'.'.$team->getKey();
        $attributes = request()->attributes;

        if (! $attributes->has($key)) {
            $attributes->set($key, $count());
        }

        return (int) $attributes->get($key);
    }

    /**
     * Unordered base queries for every My work section; null when the user cannot act on that section.
     *
     * @return array{
     *     tasks: Builder<CaseTask>,
     *     follow_ups: Builder<PatientFollowUp>,
     *     appointments: Builder<CaseAppointment>|null,
     *     messages: Builder<CaseCommunication>|null,
     *     replies: Builder<PatientCase>|null,
     *     documents: Builder<PatientDocument>|null,
     *     proposal_approvals: Builder<TreatmentProposal>|null,
     *     recommendation_approvals: Builder<ClinicalRecommendation>|null,
     * }
     */
    public static function attentionQueries(User $user, Team $team): array
    {
        $visibleCaseIds = fn () => PatientCase::query()
            ->visibleToStaff($user, $team)
            ->select('patient_cases.id');

        $canManageCommunications = $user->hasTeamPermission($team, TeamPermission::ManageCommunications);
        $communicationsEnabled = $team->moduleEnabled('communications');
        $canApproveClinical = $user->hasTeamPermission($team, TeamPermission::ApproveClinical);

        /**
         * Cases this person looks after: their own, plus unassigned ones they are able to pick up.
         */
        $myCases = function (Builder $query) use ($user, $canManageCommunications): void {
            $query->where(function (Builder $builder) use ($user, $canManageCommunications): void {
                $builder->where('patient_cases.assigned_coordinator_id', $user->id)
                    ->orWhere('patient_cases.assigned_doctor_id', $user->id);

                if ($canManageCommunications) {
                    $builder->orWhereNull('patient_cases.assigned_coordinator_id');
                }
            });
        };

        return [
            'tasks' => CaseTask::query()
                ->where('team_id', $team->id)
                ->where('assigned_user_id', $user->id)
                ->whereIn('status', [CaseTaskStatus::Open, CaseTaskStatus::InProgress, CaseTaskStatus::Waiting])
                ->whereIn('patient_case_id', $visibleCaseIds()),

            'follow_ups' => PatientFollowUp::query()
                ->where('team_id', $team->id)
                ->where('assigned_user_id', $user->id)
                ->whereIn('status', [FollowUpStatus::Scheduled, FollowUpStatus::InProgress])
                ->whereIn('patient_case_id', $visibleCaseIds()),

            'appointments' => ! $team->moduleEnabled('appointments') ? null : CaseAppointment::query()
                ->where('team_id', $team->id)
                ->whereIn('status', [AppointmentStatus::Proposed, AppointmentStatus::AwaitingConfirmation])
                ->whereIn('patient_case_id', $visibleCaseIds()),

            'messages' => ! ($communicationsEnabled && $canManageCommunications) ? null : CaseCommunication::query()
                ->where('team_id', $team->id)
                ->whereIn('status', [CommunicationStatus::Draft, CommunicationStatus::AwaitingApproval])
                ->whereIn('patient_case_id', $visibleCaseIds()),

            'replies' => ! $communicationsEnabled ? null : PatientCase::query()
                ->visibleToStaff($user, $team)
                ->select('patient_cases.*')
                ->where(function (Builder $builder) use ($user, $canManageCommunications): void {
                    $builder->where('patient_cases.assigned_coordinator_id', $user->id);

                    if ($canManageCommunications) {
                        $builder->orWhereNull('patient_cases.assigned_coordinator_id');
                    }
                })
                ->where(self::latestPatientFacingMessage('direction'), CommunicationDirection::Inbound->value),

            'documents' => ! ($user->hasTeamPermission($team, TeamPermission::ReviewAiOutputs)
                && $user->hasTeamPermission($team, TeamPermission::ViewDocuments)) ? null : PatientDocument::query()
                ->where('team_id', $team->id)
                ->whereIn('patient_case_id', PatientCase::query()
                    ->visibleToStaff($user, $team)
                    ->tap($myCases)
                    ->select('patient_cases.id'))
                ->where(function (Builder $builder): void {
                    $builder->whereHas('currentExtraction', fn (Builder $extraction) => $extraction->where('review_status', 'unreviewed'))
                        ->orWhereHas('currentSummary', fn (Builder $summary) => $summary->where('review_status', 'unreviewed'));
                }),

            'proposal_approvals' => ! ($canApproveClinical && $team->moduleEnabled('proposals')) ? null : TreatmentProposal::query()
                ->where('team_id', $team->id)
                ->where('is_current', true)
                ->where('status', TreatmentProposalStatus::AwaitingClinicalApproval)
                ->whereIn('patient_case_id', $visibleCaseIds()),

            'recommendation_approvals' => ! $canApproveClinical ? null : ClinicalRecommendation::query()
                ->where('team_id', $team->id)
                ->where('is_current', true)
                ->where('is_ai_generated', false)
                ->where('status', ClinicalRecommendationStatus::AwaitingApproval)
                ->whereIn('patient_case_id', $visibleCaseIds()),
        ];
    }

    /**
     * The newest message the patient could see or sent (no internal notes, no unsent drafts) for each case row.
     *
     * @return Builder<CaseCommunication>
     */
    protected static function latestPatientFacingMessage(string $column): Builder
    {
        return CaseCommunication::query()
            ->select($column)
            ->whereColumn('case_communications.patient_case_id', 'patient_cases.id')
            ->where('direction', '!=', CommunicationDirection::Internal->value)
            ->whereNotIn('status', [
                CommunicationStatus::Draft->value,
                CommunicationStatus::AwaitingApproval->value,
                CommunicationStatus::Failed->value,
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(1);
    }
}
