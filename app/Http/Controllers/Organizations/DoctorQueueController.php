<?php

namespace App\Http\Controllers\Organizations;

use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Models\PatientCase;
use App\Models\Team;
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
        ]);
    }
}
