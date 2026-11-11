<?php

namespace App\Http\Controllers\Organizations;

use App\Enums\AppointmentStatus;
use App\Enums\CaseTaskStatus;
use App\Enums\CommunicationStatus;
use App\Enums\FollowUpStatus;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Models\CaseAppointment;
use App\Models\CaseCommunication;
use App\Models\CaseTask;
use App\Models\PatientCase;
use App\Models\PatientFollowUp;
use App\Models\Team;
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

        $visibleCaseIds = fn () => PatientCase::query()
            ->visibleToStaff($user, $current_team)
            ->select('patient_cases.id');

        $caseSummary = fn ($case) => [
            'reference' => $case->reference,
            'patient_name' => $case->patient?->fullName(),
            'url' => route('organizations.cases.show', ['current_team' => $current_team->slug, 'case' => $case->reference]),
        ];

        $tasks = CaseTask::query()
            ->where('team_id', $current_team->id)
            ->where('assigned_user_id', $user->id)
            ->whereIn('status', [CaseTaskStatus::Open, CaseTaskStatus::InProgress, CaseTaskStatus::Waiting])
            ->whereIn('patient_case_id', $visibleCaseIds())
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
            ]);

        $followUps = PatientFollowUp::query()
            ->where('team_id', $current_team->id)
            ->where('assigned_user_id', $user->id)
            ->whereIn('status', [FollowUpStatus::Scheduled, FollowUpStatus::InProgress])
            ->whereIn('patient_case_id', $visibleCaseIds())
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
            ]);

        $appointments = ! $current_team->moduleEnabled('appointments') ? collect() : CaseAppointment::query()
            ->where('team_id', $current_team->id)
            ->whereIn('status', [AppointmentStatus::Proposed, AppointmentStatus::AwaitingConfirmation])
            ->whereIn('patient_case_id', $visibleCaseIds())
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

        $canApproveMessages = $current_team->moduleEnabled('communications')
            && $user->hasTeamPermission($current_team, TeamPermission::ManageCommunications);

        $messages = $canApproveMessages
            ? CaseCommunication::query()
                ->where('team_id', $current_team->id)
                ->whereIn('status', [CommunicationStatus::Draft, CommunicationStatus::AwaitingApproval])
                ->whereIn('patient_case_id', $visibleCaseIds())
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
                ])
            : collect();

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
            ],
            'canApproveMessages' => $canApproveMessages,
        ]);
    }
}
