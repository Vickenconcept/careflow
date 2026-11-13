<?php

namespace App\Http\Controllers\Organizations;

use App\Domains\Cases\CaseStatusTransitionMap;
use App\Enums\AppointmentConfirmationSource;
use App\Enums\AppointmentType;
use App\Enums\CasePriority;
use App\Enums\CaseStatus;
use App\Enums\CaseTaskPriority;
use App\Enums\CaseTaskStatus;
use App\Enums\ClinicalApprovalOutcome;
use App\Enums\ClinicalRecommendationStatus;
use App\Enums\CommunicationChannel;
use App\Enums\DoctorReviewOutcome;
use App\Enums\FollowUpStatus;
use App\Enums\FollowUpType;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Enums\TravelArrangementStatus;
use App\Enums\VisaAssistanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\FilterPatientCasesRequest;
use App\Models\Membership;
use App\Models\PatientCase;
use App\Models\SavedCaseView;
use App\Models\Team;
use App\Models\User;
use App\Queries\PatientCaseIndexQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PatientCaseController extends Controller
{
    public function index(
        FilterPatientCasesRequest $request,
        Team $current_team,
        PatientCaseIndexQuery $query,
        CaseStatusTransitionMap $transitions,
    ): Response {
        Gate::authorize('viewAny', [PatientCase::class, $current_team]);

        $filters = $request->filters();
        $user = $request->user();

        $cases = $query
            ->paginate($user, $current_team, $filters)
            ->through(fn (PatientCase $case) => [
                'id' => $case->id,
                'reference' => $case->reference,
                'status' => $case->status->value,
                'status_label' => $case->status->label(),
                'priority' => $case->priority->value,
                'patient_name' => $case->patient->fullName(),
                'country' => $case->patient->country_of_residence,
                'preferred_language' => $case->patient->preferred_language,
                'department' => $case->department?->name,
                'assigned_coordinator_id' => $case->assigned_coordinator_id,
                'assigned_coordinator' => $case->assignedCoordinator?->name,
                'assigned_doctor_id' => $case->assigned_doctor_id,
                'assigned_doctor' => $case->assignedDoctor?->name,
                'available_transitions' => collect($transitions->allowedFrom($case->status))
                    ->map(fn (CaseStatus $status) => [
                        'value' => $status->value,
                        'label' => $status->label(),
                    ])
                    ->values(),
                'last_activity_at' => $case->last_activity_at?->toIso8601String(),
                'is_demo' => $case->is_demo,
                'documents_count' => (int) $case->documents_count,
            ]);

        $assignableMembers = $current_team->members()
            ->orderBy('name')
            ->get()
            ->map(function (User $member) {
                /** @var Membership $membership */
                $membership = $member->getRelation('pivot');

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $membership->role->value,
                    'role_label' => $membership->role->label(),
                ];
            });

        return Inertia::render('organizations/cases/index', [
            'team' => [
                'id' => $current_team->id,
                'name' => $current_team->name,
                'slug' => $current_team->slug,
            ],
            'cases' => $cases,
            'filters' => $filters,
            'quickViewCounts' => $query->quickViewCounts($user, $current_team),
            'savedViews' => SavedCaseView::query()
                ->forTeam($current_team)
                ->where('user_id', $user->id)
                ->orderBy('name')
                ->get()
                ->map(fn (SavedCaseView $view) => [
                    'id' => $view->id,
                    'name' => $view->name,
                    'filters' => $view->filters,
                ])
                ->values(),
            'filterOptions' => [
                'statuses' => collect(CaseStatus::cases())->map(fn (CaseStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])->values(),
                'priorities' => collect(CasePriority::cases())->map(fn (CasePriority $priority) => [
                    'value' => $priority->value,
                    'label' => $priority->label(),
                ])->values(),
                'departments' => $current_team->departments()
                    ->get(['id', 'name'])
                    ->map(fn ($department) => [
                        'id' => $department->id,
                        'name' => $department->name,
                    ]),
                'assignments' => [
                    ['value' => 'mine', 'label' => 'My assigned cases'],
                    ['value' => 'unassigned', 'label' => 'Unassigned coordinator'],
                    ['value' => 'unassigned_doctor', 'label' => 'Unassigned doctor'],
                ],
                'sorts' => [
                    ['value' => 'recent', 'label' => 'Most recent activity'],
                    ['value' => 'oldest', 'label' => 'Oldest activity'],
                    ['value' => 'reference', 'label' => 'Reference'],
                    ['value' => 'status', 'label' => 'Status'],
                ],
            ],
            'assignableMembers' => [
                'coordinators' => $assignableMembers
                    ->filter(fn (array $member) => in_array($member['role'], [
                        TeamRole::Owner->value,
                        TeamRole::Admin->value,
                        TeamRole::Coordinator->value,
                    ], true))
                    ->values(),
                'doctors' => $assignableMembers
                    ->filter(fn (array $member) => in_array($member['role'], [
                        TeamRole::Doctor->value,
                        TeamRole::Owner->value,
                        TeamRole::Admin->value,
                    ], true))
                    ->values(),
            ],
            'permissions' => [
                'canAssignCases' => $user->hasTeamPermission($current_team, TeamPermission::AssignCases),
                'canUpdateStatus' => $user->hasTeamPermission($current_team, TeamPermission::UpdateCaseStatus),
                'canExport' => $user->hasTeamPermission($current_team, TeamPermission::ManageCases),
            ],
        ]);
    }

    public function show(
        Request $request,
        Team $current_team,
        PatientCase $case,
        CaseStatusTransitionMap $transitions,
    ): Response {
        abort_unless($case->team_id === $current_team->id, 404);
        Gate::authorize('view', $case);

        $canViewClinical = $request->user()->can('viewClinical', $case);
        $canManageProposals = $request->user()->can('manageProposals', $case);
        $canApproveProposals = $request->user()->can('approveProposals', $case);
        $canViewProposals = $current_team->moduleEnabled('proposals') && ($canManageProposals || $canApproveProposals || $request->user()->can('manage', $case));
        $canManageAppointments = $request->user()->can('manageAppointments', $case);
        $canViewAppointments = $current_team->moduleEnabled('appointments') && ($canManageAppointments || $request->user()->can('manage', $case) || $request->user()->can('view', $case));
        $canManageTravel = $request->user()->can('manageTravel', $case);
        $canManageTasks = $request->user()->can('manageTasks', $case);
        $canManageCommunications = $request->user()->can('manageCommunications', $case);
        $canViewOps = $canManageTravel || $canManageTasks || $canManageCommunications
            || $request->user()->can('manage', $case)
            || $request->user()->can('view', $case);

        $case->load([
            'patient',
            'enquiry.department',
            'department:id,name',
            'documents' => fn ($query) => $query->with(['currentExtraction', 'currentSummary'])->orderByDesc('uploaded_at')->orderByDesc('id'),
            'assignedCoordinator:id,name,email',
            'assignedDoctor:id,name,email',
            'activities' => fn ($query) => $query
                ->with('actor:id,name')
                ->limit(50),
        ]);

        if ($canViewProposals) {
            $case->load([
                'treatmentProposals' => fn ($query) => $query
                    ->with([
                        'items',
                        'department:id,name',
                        'responsiblePhysician:id,name,email',
                        'clinicalRecommendation:id,uuid,version,status',
                        'createdBy:id,name',
                        'approvedBy:id,name',
                    ])
                    ->limit(20),
            ]);
        }

        if ($canViewAppointments) {
            $case->load([
                'appointments' => fn ($query) => $query
                    ->with([
                        'department:id,name',
                        'doctor:id,name,email',
                        'createdBy:id,name',
                        'confirmedBy:id,name',
                    ])
                    ->limit(30),
            ]);
        }

        if ($canViewOps) {
            $case->load([
                'travelArrangement.assignedCoordinator:id,name,email',
                'tasks' => fn ($query) => $query
                    ->with(['assignedUser:id,name,email', 'createdBy:id,name'])
                    ->limit(40),
                'followUps' => fn ($query) => $query
                    ->with(['assignedUser:id,name,email', 'createdBy:id,name', 'completedBy:id,name'])
                    ->limit(40),
                'communications' => fn ($query) => $query
                    ->with(['createdBy:id,name', 'approvedBy:id,name'])
                    ->limit(40),
            ]);
        }

        if ($canViewClinical) {
            $case->load([
                'doctorReviews' => fn ($query) => $query
                    ->with('reviewer:id,name,email')
                    ->limit(20),
                'clinicalObservations' => fn ($query) => $query
                    ->with('author:id,name')
                    ->limit(30),
                'clinicalRecommendations' => fn ($query) => $query
                    ->with([
                        'author:id,name',
                        'approvals' => fn ($approvals) => $approvals
                            ->with('approver:id,name')
                            ->latest('decided_at'),
                    ])
                    ->limit(20),
            ]);
        }

        $assignableMembers = $current_team->members()
            ->orderBy('name')
            ->get()
            ->map(function (User $member) {
                /** @var Membership $membership */
                $membership = $member->getRelation('pivot');

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $membership->role->value,
                    'role_label' => $membership->role->label(),
                ];
            });

        return Inertia::render('organizations/cases/show', [
            'team' => [
                'id' => $current_team->id,
                'name' => $current_team->name,
                'slug' => $current_team->slug,
            ],
            'case' => [
                'id' => $case->id,
                'reference' => $case->reference,
                'status' => $case->status->value,
                'status_label' => $case->status->label(),
                'priority' => $case->priority->value,
                'priority_label' => $case->priority->label(),
                'last_activity_at' => $case->last_activity_at?->toIso8601String(),
                'is_demo' => $case->is_demo,
                'patient' => [
                    'uuid' => $case->patient->uuid,
                    'full_name' => $case->patient->fullName(),
                    'email' => $case->patient->email,
                    'phone' => $case->patient->phone,
                    'date_of_birth' => $case->patient->date_of_birth?->toDateString(),
                    'gender' => $case->patient->gender?->value,
                    'country_of_residence' => $case->patient->country_of_residence,
                    'preferred_language' => $case->patient->preferred_language,
                ],
                'enquiry' => [
                    'reason_for_contact' => $case->enquiry->reason_for_contact,
                    'diagnosis' => $case->enquiry->diagnosis,
                    'symptoms_or_history' => $case->enquiry->symptoms_or_history,
                    'previous_procedures' => $case->enquiry->previous_procedures,
                    'current_medications' => $case->enquiry->current_medications,
                    'known_allergies' => $case->enquiry->known_allergies,
                    'additional_comments' => $case->enquiry->additional_comments,
                    'submitted_at' => $case->enquiry->submitted_at->toIso8601String(),
                    'department' => $case->enquiry->department?->name,
                ],
                'assignments' => [
                    'coordinator' => $case->assignedCoordinator?->only(['id', 'name', 'email']),
                    'doctor' => $case->assignedDoctor?->only(['id', 'name', 'email']),
                ],
                'documents' => $case->documents->map(fn ($document) => [
                    'uuid' => $document->uuid,
                    'original_filename' => $document->original_filename,
                    'category' => $document->category->label(),
                    'processing_status' => $document->processing_status->label(),
                    'size_bytes' => $document->size_bytes,
                    'ai_review' => match (true) {
                        $document->currentExtraction === null && $document->currentSummary === null => 'none',
                        $document->currentExtraction?->review_status === 'unreviewed' || $document->currentSummary?->review_status === 'unreviewed' => 'unreviewed',
                        default => 'reviewed',
                    },
                    'uploaded_at' => $document->uploaded_at->toIso8601String(),
                    'ai_state' => match (true) {
                        $document->processing_status->isInProgress() => 'processing',
                        $document->currentExtraction !== null || $document->currentSummary !== null => 'analysed',
                        $document->aiProcessingBlockedReason() !== null => 'unavailable',
                        default => 'not_analysed',
                    },
                    'review_url' => route('organizations.documents.show', [
                        'current_team' => $current_team->slug,
                        'case' => $case->reference,
                        'document' => $document->uuid,
                    ]),
                    'download_url' => $request->user()->can('download', $document)
                        ? route('organizations.documents.download', [
                            'current_team' => $current_team->slug,
                            'case' => $case->reference,
                            'document' => $document->uuid,
                        ])
                        : null,
                ]),
                'activities' => $case->activities->map(fn ($activity) => [
                    'id' => $activity->id,
                    'type' => $activity->activity_type->value,
                    'type_label' => $activity->activity_type->label(),
                    'summary' => $activity->summary,
                    'actor' => $activity->actor?->name,
                    'occurred_at' => $activity->occurred_at->toIso8601String(),
                    'is_patient_visible' => $activity->is_patient_visible,
                    'properties' => $activity->properties,
                ]),
                'clinical' => $canViewClinical ? [
                    'disclaimer' => 'Clinical evaluations and approvals are human-authored. AI document outputs are never clinically approved content.',
                    'reviews' => $case->doctorReviews->map(fn ($review) => [
                        'uuid' => $review->uuid,
                        'status' => $review->status->value,
                        'status_label' => $review->status->label(),
                        'outcome' => $review->outcome?->value,
                        'outcome_label' => $review->outcome?->label(),
                        'findings' => $review->findings,
                        'additional_information_request' => $review->additional_information_request,
                        'reviewer' => $review->reviewer?->only(['id', 'name', 'email']),
                        'started_at' => $review->started_at->toIso8601String(),
                        'completed_at' => $review->completed_at?->toIso8601String(),
                    ]),
                    'observations' => $case->clinicalObservations->map(fn ($observation) => [
                        'id' => $observation->id,
                        'body' => $observation->body,
                        'visibility' => $observation->visibility,
                        'author' => $observation->author?->name,
                        'observed_at' => $observation->observed_at->toIso8601String(),
                    ]),
                    'recommendations' => $case->clinicalRecommendations->map(fn ($recommendation) => [
                        'uuid' => $recommendation->uuid,
                        'version' => $recommendation->version,
                        'status' => $recommendation->status->value,
                        'status_label' => $recommendation->status->label(),
                        'recommendation_text' => $recommendation->recommendation_text,
                        'supporting_document_uuids' => $recommendation->supporting_document_uuids ?? [],
                        'is_ai_generated' => $recommendation->is_ai_generated,
                        'is_current' => $recommendation->is_current,
                        'author' => $recommendation->author?->name,
                        'approvals' => $recommendation->approvals->map(fn ($approval) => [
                            'id' => $approval->id,
                            'outcome' => $approval->outcome->value,
                            'outcome_label' => $approval->outcome->label(),
                            'notes' => $approval->notes,
                            'approver' => $approval->approver?->name,
                            'decided_at' => $approval->decided_at->toIso8601String(),
                        ]),
                    ]),
                    'urls' => [
                        'store_observation' => route('organizations.clinical.observations.store', [
                            'current_team' => $current_team->slug,
                            'case' => $case->reference,
                        ]),
                        'store_recommendation' => route('organizations.clinical.recommendations.store', [
                            'current_team' => $current_team->slug,
                            'case' => $case->reference,
                        ]),
                        'store_review' => route('organizations.clinical.reviews.store', [
                            'current_team' => $current_team->slug,
                            'case' => $case->reference,
                        ]),
                    ],
                    'outcome_options' => collect(DoctorReviewOutcome::cases())->map(fn (DoctorReviewOutcome $outcome) => [
                        'value' => $outcome->value,
                        'label' => $outcome->label(),
                    ])->values(),
                    'approval_outcome_options' => collect(ClinicalApprovalOutcome::cases())->map(fn (ClinicalApprovalOutcome $outcome) => [
                        'value' => $outcome->value,
                        'label' => $outcome->label(),
                    ])->values(),
                ] : null,
                'proposals' => $canViewProposals ? [
                    'disclaimer' => 'Treatment proposals are administrative packages. Clinical recommendations remain separately approved by authorized clinicians.',
                    'items' => $case->treatmentProposals->map(fn ($proposal) => [
                        'uuid' => $proposal->uuid,
                        'reference' => $proposal->reference,
                        'version' => $proposal->version,
                        'status' => $proposal->status->value,
                        'status_label' => $proposal->status->label(),
                        'proposed_procedure' => $proposal->proposed_procedure,
                        'estimated_cost' => $proposal->estimated_cost,
                        'currency' => $proposal->currency,
                        'expected_duration' => $proposal->expected_duration,
                        'required_appointments' => $proposal->required_appointments,
                        'additional_information' => $proposal->additional_information,
                        'valid_until' => $proposal->valid_until?->toDateString(),
                        'is_current' => $proposal->is_current,
                        'department' => $proposal->department?->name,
                        'responsible_physician' => $proposal->responsiblePhysician?->only(['id', 'name', 'email']),
                        'clinical_recommendation' => $proposal->clinicalRecommendation ? [
                            'id' => $proposal->clinicalRecommendation->id,
                            'uuid' => $proposal->clinicalRecommendation->uuid,
                            'version' => $proposal->clinicalRecommendation->version,
                            'status' => $proposal->clinicalRecommendation->status->value,
                        ] : null,
                        'created_by' => $proposal->createdBy?->name,
                        'approved_by' => $proposal->approvedBy?->name,
                        'approved_at' => $proposal->approved_at?->toIso8601String(),
                        'sent_at' => $proposal->sent_at?->toIso8601String(),
                        'patient_response' => $proposal->patient_response?->value,
                        'line_items' => $proposal->items->map(fn ($item) => [
                            'id' => $item->id,
                            'description' => $item->description,
                            'quantity' => $item->quantity,
                            'unit_cost' => $item->unit_cost,
                            'currency' => $item->currency,
                        ]),
                        'transition_url' => route('organizations.proposals.transition', [
                            'current_team' => $current_team->slug,
                            'case' => $case->reference,
                            'proposal' => $proposal->uuid,
                        ]),
                    ]),
                    'approved_recommendations' => $case->clinicalRecommendations()
                        ->where('status', ClinicalRecommendationStatus::Approved)
                        ->orderByDesc('version')
                        ->get(['id', 'uuid', 'version', 'recommendation_text'])
                        ->map(fn ($recommendation) => [
                            'id' => $recommendation->id,
                            'uuid' => $recommendation->uuid,
                            'version' => $recommendation->version,
                            'label' => sprintf('v%d — %s', $recommendation->version, str($recommendation->recommendation_text)->limit(80)),
                        ])
                        ->values(),
                    'urls' => [
                        'store' => route('organizations.proposals.store', [
                            'current_team' => $current_team->slug,
                            'case' => $case->reference,
                        ]),
                    ],
                    'defaults' => [
                        'department_id' => $case->department_id,
                        'responsible_physician_id' => $case->assigned_doctor_id,
                        'currency' => $current_team->organizationSetting?->default_currency ?? 'EUR',
                    ],
                ] : null,
                'appointments' => $canViewAppointments ? [
                    'disclaimer' => 'Appointments are administrative planning records. Confirmed status requires hospital/HIS verification and is not an automatic booking claim.',
                    'items' => $case->appointments->map(fn ($appointment) => [
                        'uuid' => $appointment->uuid,
                        'status' => $appointment->status->value,
                        'status_label' => $appointment->status->label(),
                        'appointment_type' => $appointment->appointment_type->value,
                        'appointment_type_label' => $appointment->appointment_type->label(),
                        'proposed_starts_at' => $appointment->proposed_starts_at?->toIso8601String(),
                        'proposed_ends_at' => $appointment->proposed_ends_at?->toIso8601String(),
                        'confirmed_starts_at' => $appointment->confirmed_starts_at?->toIso8601String(),
                        'confirmed_ends_at' => $appointment->confirmed_ends_at?->toIso8601String(),
                        'location' => $appointment->location,
                        'meeting_instructions' => $appointment->meeting_instructions,
                        'external_his_reference' => $appointment->external_his_reference,
                        'confirmation_source' => $appointment->confirmation_source?->value,
                        'confirmation_source_label' => $appointment->confirmation_source?->label(),
                        'hospital_slot_verified' => $appointment->hospital_slot_verified,
                        'confirmed_at' => $appointment->confirmed_at?->toIso8601String(),
                        'notes' => $appointment->notes,
                        'department' => $appointment->department?->name,
                        'doctor' => $appointment->doctor?->only(['id', 'name', 'email']),
                        'created_by' => $appointment->createdBy?->name,
                        'confirmed_by' => $appointment->confirmedBy?->name,
                        'transition_url' => route('organizations.appointments.transition', [
                            'current_team' => $current_team->slug,
                            'case' => $case->reference,
                            'appointment' => $appointment->uuid,
                        ]),
                    ]),
                    'type_options' => AppointmentType::options(),
                    'confirmation_source_options' => AppointmentConfirmationSource::options(),
                    'urls' => [
                        'store' => route('organizations.appointments.store', [
                            'current_team' => $current_team->slug,
                            'case' => $case->reference,
                        ]),
                    ],
                    'defaults' => [
                        'department_id' => $case->department_id,
                        'doctor_id' => $case->assigned_doctor_id,
                    ],
                ] : null,
                'travel' => $canViewOps && $current_team->moduleEnabled('travel') ? [
                    'disclaimer' => 'Travel records are administrative only. Do not store passport numbers here.',
                    'arrangement' => $case->travelArrangement ? [
                        'uuid' => $case->travelArrangement->uuid,
                        'status' => $case->travelArrangement->status->value,
                        'status_label' => $case->travelArrangement->status->label(),
                        'expected_arrival_date' => $case->travelArrangement->expected_arrival_date?->toDateString(),
                        'expected_departure_date' => $case->travelArrangement->expected_departure_date?->toDateString(),
                        'flight_information' => $case->travelArrangement->flight_information,
                        'airport_pickup_required' => $case->travelArrangement->airport_pickup_required,
                        'airport_pickup_details' => $case->travelArrangement->airport_pickup_details,
                        'transfer_arrangements' => $case->travelArrangement->transfer_arrangements,
                        'accommodation' => $case->travelArrangement->accommodation,
                        'hotel_details' => $case->travelArrangement->hotel_details,
                        'visa_assistance_status' => $case->travelArrangement->visa_assistance_status->value,
                        'visa_assistance_status_label' => $case->travelArrangement->visa_assistance_status->label(),
                        'special_administrative_requirements' => $case->travelArrangement->special_administrative_requirements,
                        'checklist' => $case->travelArrangement->checklist ?? [],
                        'notes' => $case->travelArrangement->notes,
                        'assigned_coordinator' => $case->travelArrangement->assignedCoordinator?->only(['id', 'name', 'email']),
                    ] : null,
                    'status_options' => TravelArrangementStatus::options(),
                    'visa_options' => VisaAssistanceStatus::options(),
                    'urls' => [
                        'upsert' => route('organizations.travel.upsert', [
                            'current_team' => $current_team->slug,
                            'case' => $case->reference,
                        ]),
                    ],
                ] : null,
                'tasks' => $canViewOps ? [
                    'items' => $case->tasks->map(fn ($task) => [
                        'uuid' => $task->uuid,
                        'title' => $task->title,
                        'description' => $task->description,
                        'status' => $task->status->value,
                        'status_label' => $task->status->label(),
                        'priority' => $task->priority->value,
                        'priority_label' => $task->priority->label(),
                        'due_at' => $task->due_at?->toIso8601String(),
                        'completed_at' => $task->completed_at?->toIso8601String(),
                        'assigned_user' => $task->assignedUser?->only(['id', 'name', 'email']),
                        'created_by' => $task->createdBy?->name,
                        'transition_url' => route('organizations.tasks.transition', [
                            'current_team' => $current_team->slug,
                            'case' => $case->reference,
                            'task' => $task->uuid,
                        ]),
                    ]),
                    'status_options' => CaseTaskStatus::options(),
                    'priority_options' => CaseTaskPriority::options(),
                    'urls' => [
                        'store' => route('organizations.tasks.store', [
                            'current_team' => $current_team->slug,
                            'case' => $case->reference,
                        ]),
                    ],
                ] : null,
                'follow_ups' => $canViewOps ? [
                    'items' => $case->followUps->map(fn ($followUp) => [
                        'uuid' => $followUp->uuid,
                        'title' => $followUp->title,
                        'description' => $followUp->description,
                        'follow_up_type' => $followUp->follow_up_type->value,
                        'follow_up_type_label' => $followUp->follow_up_type->label(),
                        'status' => $followUp->status->value,
                        'status_label' => $followUp->status->label(),
                        'due_at' => $followUp->due_at?->toIso8601String(),
                        'completed_at' => $followUp->completed_at?->toIso8601String(),
                        'outcome_notes' => $followUp->outcome_notes,
                        'is_overdue' => $followUp->isOverdue(),
                        'assigned_user' => $followUp->assignedUser?->only(['id', 'name', 'email']),
                        'created_by' => $followUp->createdBy?->name,
                        'completed_by' => $followUp->completedBy?->name,
                        'update_url' => route('organizations.follow-ups.update', [
                            'current_team' => $current_team->slug,
                            'case' => $case->reference,
                            'followUp' => $followUp->uuid,
                        ]),
                        'complete_url' => route('organizations.follow-ups.complete', [
                            'current_team' => $current_team->slug,
                            'case' => $case->reference,
                            'followUp' => $followUp->uuid,
                        ]),
                    ]),
                    'type_options' => FollowUpType::options(),
                    'status_options' => FollowUpStatus::options(),
                    'urls' => [
                        'store' => route('organizations.follow-ups.store', [
                            'current_team' => $current_team->slug,
                            'case' => $case->reference,
                        ]),
                    ],
                ] : null,
                'communications' => $canViewOps && $current_team->moduleEnabled('communications') ? [
                    'disclaimer' => 'External messages require staff approval. AI drafts are administrative only and must not send clinical advice.',
                    'items' => $case->communications->map(fn ($communication) => [
                        'uuid' => $communication->uuid,
                        'channel' => $communication->channel->value,
                        'channel_label' => $communication->channel->label(),
                        'direction' => $communication->direction->value,
                        'status' => $communication->status->value,
                        'status_label' => $communication->status->label(),
                        'language' => $communication->language,
                        'subject' => $communication->subject,
                        'body' => $communication->body,
                        'recipient' => $communication->recipient,
                        'is_ai_draft' => $communication->is_ai_draft,
                        'requires_approval' => $communication->requires_approval,
                        'created_by' => $communication->createdBy?->name,
                        'approved_by' => $communication->approvedBy?->name,
                        'created_at' => $communication->created_at?->toIso8601String(),
                        'approve_url' => route('organizations.communications.approve', [
                            'current_team' => $current_team->slug,
                            'case' => $case->reference,
                            'communication' => $communication->uuid,
                        ]),
                    ]),
                    'channel_options' => CommunicationChannel::options(),
                    'urls' => [
                        'store' => route('organizations.communications.store', [
                            'current_team' => $current_team->slug,
                            'case' => $case->reference,
                        ]),
                    ],
                ] : null,
            ],
            'availableStatusTransitions' => collect($transitions->allowedFrom($case->status))
                ->map(fn (CaseStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->values(),
            'assignableMembers' => [
                'coordinators' => $assignableMembers
                    ->filter(fn (array $member) => in_array($member['role'], [
                        TeamRole::Owner->value,
                        TeamRole::Admin->value,
                        TeamRole::Coordinator->value,
                    ], true))
                    ->values(),
                'doctors' => $assignableMembers
                    ->filter(fn (array $member) => in_array($member['role'], [
                        TeamRole::Doctor->value,
                        TeamRole::Owner->value,
                        TeamRole::Admin->value,
                    ], true))
                    ->values(),
                'staff' => $assignableMembers->values(),
            ],
            'permissions' => [
                'canManageCases' => $request->user()->can('manage', $case),
                'canAssignCases' => $request->user()->can('assign', $case),
                'canUpdateStatus' => $request->user()->can('updateStatus', $case),
                'canViewClinical' => $canViewClinical,
                'canReviewClinical' => $request->user()->can('reviewClinical', $case),
                'canApproveClinical' => $request->user()->can('approveClinical', $case),
                'canManageProposals' => $canManageProposals,
                'canApproveProposals' => $canApproveProposals,
                'canManageAppointments' => $canManageAppointments,
                'canManageTravel' => $canManageTravel,
                'canManageTasks' => $canManageTasks,
                'canManageCommunications' => $canManageCommunications,
            ],
        ]);
    }
}
