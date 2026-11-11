<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Patients\RecordPatientPortalSubmission;
use App\Enums\AppointmentStatus;
use App\Enums\CommunicationChannel;
use App\Enums\CommunicationDirection;
use App\Enums\CommunicationStatus;
use App\Enums\DocumentCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ReplyPortalMessageRequest;
use App\Http\Requests\Portal\UploadPortalDocumentsRequest;
use App\Models\CaseAppointment;
use App\Models\CaseCommunication;
use App\Models\PatientCase;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class PatientCaseTrackingController extends Controller
{
    /**
     * Secure patient-facing case status. Never expose clinical notes or AI internals.
     */
    public function show(Request $request, Team $organization, string $reference): Response
    {
        $token = $request->string('token')->toString();

        abort_unless($token !== '', 404);

        $case = PatientCase::query()
            ->forTeam($organization)
            ->where('reference', $reference)
            ->with(['patient:id,first_name,last_name,preferred_language', 'department:id,name'])
            ->firstOrFail();

        abort_unless($case->portalAccessTokenIsValid($token), 403);

        return Inertia::render('portal/case-tracking', [
            'organization' => [
                'name' => $organization->organizationSetting?->legal_name ?: $organization->name,
                'slug' => $organization->slug,
                'logo_url' => $organization->organizationSetting?->logo_path ? route('portal.logo', ['organization' => $organization->slug]) : null,
            ],
            'case' => [
                'reference' => $case->reference,
                'status' => $case->status->patientLabel(),
                'state' => $case->status->patientStateKey(),
                'stage' => $case->status->patientJourneyStage(),
                'department' => $case->department?->name,
                'patient_first_name' => $case->patient->first_name,
                'preferred_language' => $case->patient->preferred_language,
                'last_activity_at' => $case->last_activity_at?->toIso8601String(),
            ],
            'messages' => $organization->moduleEnabled('communications') ? $this->portalMessages($case) : [],
            'appointments' => $organization->moduleEnabled('appointments') ? $this->portalAppointments($case) : [],
            'timezone' => $organization->organizationSetting?->timezone ?: 'UTC',
            'notice' => session('portal_notice'),
            'canRespond' => $case->status->patientJourneyStage() !== null,
            'canReply' => $organization->moduleEnabled('communications') && $case->status->patientJourneyStage() !== null,
        ]);
    }

    /**
     * The patient-visible thread: approved staff messages addressed to the portal,
     * plus the patient's own replies. Internal notes, drafts and other channels stay hidden.
     *
     * @return list<array{id: string, subject: string|null, body: string, sent_at: string|null, from_patient: bool}>
     */
    protected function portalMessages(PatientCase $case): array
    {
        return CaseCommunication::query()
            ->where('patient_case_id', $case->id)
            ->where('channel', CommunicationChannel::PatientPortal)
            ->where(function ($query): void {
                $query->where(function ($staff): void {
                    $staff->where('direction', CommunicationDirection::Outbound)
                        ->whereIn('status', [CommunicationStatus::Approved, CommunicationStatus::Sent])
                        ->whereNotNull('approved_at');
                })->orWhere(function ($patient): void {
                    $patient->where('direction', CommunicationDirection::Inbound)
                        ->where('status', CommunicationStatus::Logged);
                });
            })
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn (CaseCommunication $message) => [
                'id' => $message->uuid,
                'subject' => $message->subject,
                'body' => $message->body,
                'sent_at' => ($message->sent_at ?? $message->approved_at ?? $message->created_at)?->toIso8601String(),
                'from_patient' => $message->direction === CommunicationDirection::Inbound,
            ])
            ->values()
            ->all();
    }

    /**
     * Only hospital-verified, confirmed appointments are shown to the patient.
     *
     * @return list<array{id: string, type: string, starts_at: string, ends_at: string|null, department: string|null, location: string|null, instructions: string|null}>
     */
    protected function portalAppointments(PatientCase $case): array
    {
        return CaseAppointment::query()
            ->where('patient_case_id', $case->id)
            ->where('status', AppointmentStatus::Confirmed)
            ->where('hospital_slot_verified', true)
            ->whereNotNull('confirmed_starts_at')
            ->with('department:id,name')
            ->orderBy('confirmed_starts_at')
            ->limit(10)
            ->get()
            ->map(fn (CaseAppointment $appointment) => [
                'id' => $appointment->uuid,
                'type' => $appointment->appointment_type->label(),
                'starts_at' => $appointment->confirmed_starts_at->toIso8601String(),
                'ends_at' => $appointment->confirmed_ends_at?->toIso8601String(),
                'department' => $appointment->department?->name,
                'location' => $appointment->location,
                'instructions' => $appointment->meeting_instructions,
            ])
            ->values()
            ->all();
    }

    public function uploadDocuments(
        UploadPortalDocumentsRequest $request,
        Team $organization,
        string $reference,
        RecordPatientPortalSubmission $submission,
    ): RedirectResponse {
        $case = $this->openCaseForToken($organization, $reference, $request->string('token')->toString());

        $files = collect($request->file('documents', []))
            ->values()
            ->map(fn (UploadedFile $file, int $index) => [
                'file' => $file,
                'category' => DocumentCategory::tryFrom((string) ($request->input('document_categories')[$index] ?? ''))
                    ?? DocumentCategory::OtherSupportingDocument,
            ])
            ->all();

        $submission->uploadDocuments($case, $files);

        return $this->backToTracking($organization, $case, $request->string('token')->toString(), 'documents');
    }

    public function reply(
        ReplyPortalMessageRequest $request,
        Team $organization,
        string $reference,
        RecordPatientPortalSubmission $submission,
    ): RedirectResponse {
        abort_unless($organization->moduleEnabled('communications'), 403);

        $case = $this->openCaseForToken($organization, $reference, $request->string('token')->toString());

        $submission->reply($case, $request->string('body')->toString());

        return $this->backToTracking($organization, $case, $request->string('token')->toString(), 'message');
    }

    protected function openCaseForToken(Team $organization, string $reference, string $token): PatientCase
    {
        $case = PatientCase::query()
            ->forTeam($organization)
            ->where('reference', $reference)
            ->firstOrFail();

        abort_unless($token !== '' && $case->portalAccessTokenIsValid($token), 403);
        abort_if($case->status->patientJourneyStage() === null, 403);

        return $case;
    }

    protected function backToTracking(Team $organization, PatientCase $case, string $token, string $notice): RedirectResponse
    {
        return redirect()
            ->route('portal.cases.show', [
                'organization' => $organization->slug,
                'reference' => $case->reference,
                'token' => $token,
            ])
            ->with('portal_notice', $notice);
    }
}
