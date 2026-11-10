<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Patients\QueuePatientDocumentForProcessing;
use App\Domains\Cases\CaseStatusTransitionMap;
use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Models\PatientCase;
use App\Models\PatientDocument;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatientDocumentController extends Controller
{
    public function show(
        Request $request,
        Team $current_team,
        PatientCase $case,
        PatientDocument $document,
        CaseStatusTransitionMap $transitions,
    ): Response {
        abort_unless($case->team_id === $current_team->id, 404);
        abort_unless($document->patient_case_id === $case->id, 404);
        Gate::authorize('view', $document);

        $document->load([
            'currentExtractedText',
            'currentExtraction',
            'currentTranslation',
            'currentSummary',
            'reviewEvents' => fn ($query) => $query->latest('reviewed_at')->limit(20),
        ]);

        return Inertia::render('organizations/documents/show', [
            'team' => [
                'id' => $current_team->id,
                'name' => $current_team->name,
                'slug' => $current_team->slug,
            ],
            'case' => [
                'reference' => $case->reference,
                'status' => $case->status->value,
                'status_label' => $case->status->label(),
                'show_url' => route('organizations.cases.show', [
                    'current_team' => $current_team->slug,
                    'case' => $case->reference,
                ]),
                'status_update_url' => route('organizations.cases.status.update', [
                    'current_team' => $current_team->slug,
                    'case' => $case->reference,
                ]),
            ],
            'availableStatusTransitions' => collect($transitions->allowedFrom($case->status))
                ->map(fn (CaseStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->values(),
            'document' => [
                'uuid' => $document->uuid,
                'original_filename' => $document->original_filename,
                'mime_type' => $document->mime_type,
                'extension' => $document->extension,
                'category' => $document->category->label(),
                'processing_status' => $document->processing_status->value,
                'processing_status_label' => $document->processing_status->label(),
                'processing_error' => $document->processing_error,
                'processing_in_progress' => $document->processing_status->isInProgress(),
                'processing_steps' => $document->processing_status->progressSteps(),
                'original_language' => $document->original_language,
                'uploaded_at' => $document->uploaded_at->toIso8601String(),
                'preview_url' => route('organizations.documents.preview', [
                    'current_team' => $current_team->slug,
                    'case' => $case->reference,
                    'document' => $document->uuid,
                ]),
                'download_url' => route('organizations.documents.download', [
                    'current_team' => $current_team->slug,
                    'case' => $case->reference,
                    'document' => $document->uuid,
                ]),
                'reprocess_url' => route('organizations.documents.reprocess', [
                    'current_team' => $current_team->slug,
                    'case' => $case->reference,
                    'document' => $document->uuid,
                ]),
                'review_update_url' => route('organizations.documents.review.update', [
                    'current_team' => $current_team->slug,
                    'case' => $case->reference,
                    'document' => $document->uuid,
                ]),
                'is_image' => in_array(strtolower($document->extension), ['jpg', 'jpeg', 'png'], true),
                'is_pdf' => strtolower($document->extension) === 'pdf',
            ],
            'ai' => [
                'provider' => (string) config('careflow.ai.provider'),
                'disclaimer_summary' => 'AI-generated draft. Requires review by an authorized healthcare professional.',
                'disclaimer_translation' => 'AI-assisted translation. Not a certified medical translation.',
                'extracted_text' => $document->currentExtractedText?->extracted_text,
                'extraction' => $document->currentExtraction ? [
                    'version' => $document->currentExtraction->version,
                    'payload' => $document->currentExtraction->effectivePayload(),
                    'original_payload' => $document->currentExtraction->payload,
                    'is_synthetic' => $document->currentExtraction->is_synthetic,
                    'review_status' => $document->currentExtraction->review_status,
                    'provider' => $document->currentExtraction->provider,
                ] : null,
                'translation' => $document->currentTranslation ? [
                    'version' => $document->currentTranslation->version,
                    'source_language' => $document->currentTranslation->source_language,
                    'target_language' => $document->currentTranslation->target_language,
                    'translated_text' => $document->currentTranslation->translated_text,
                    'disclaimer' => $document->currentTranslation->disclaimer,
                    'is_synthetic' => $document->currentTranslation->is_synthetic,
                    'provider' => $document->currentTranslation->provider,
                ] : null,
                'summary' => $document->currentSummary ? [
                    'version' => $document->currentSummary->version,
                    'sections' => $document->currentSummary->effectiveSections(),
                    'original_sections' => $document->currentSummary->sections,
                    'disclaimer' => $document->currentSummary->disclaimer,
                    'is_synthetic' => $document->currentSummary->is_synthetic,
                    'review_status' => $document->currentSummary->review_status,
                    'provider' => $document->currentSummary->provider,
                ] : null,
                'review_events' => $document->reviewEvents->map(fn ($event) => [
                    'id' => $event->id,
                    'field_path' => $event->field_path,
                    'reason' => $event->reason,
                    'reviewed_at' => $event->reviewed_at->toIso8601String(),
                ]),
            ],
            'permissions' => [
                'canReviewAi' => $request->user()->can('reviewAi', $document),
                'canDownload' => $request->user()->can('download', $document),
                'canReprocess' => $request->user()->can('reprocess', $document),
                'canUpdateStatus' => $request->user()->can('updateStatus', $case),
            ],
        ]);
    }

    public function reprocess(
        Request $request,
        Team $current_team,
        PatientCase $case,
        PatientDocument $document,
        QueuePatientDocumentForProcessing $queuePatientDocumentForProcessing,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);
        abort_unless($document->patient_case_id === $case->id, 404);
        Gate::authorize('reprocess', $document);

        $queuePatientDocumentForProcessing->handle($document);

        return back();
    }

    /**
     * Stream the original file inline for in-page preview (iframe / image).
     */
    public function preview(Request $request, Team $current_team, PatientCase $case, PatientDocument $document): StreamedResponse
    {
        abort_unless($case->team_id === $current_team->id, 404);
        abort_unless($document->patient_case_id === $case->id, 404);
        Gate::authorize('view', $document);

        return Storage::disk($document->disk)->response(
            $document->path,
            $document->original_filename,
            [
                'Content-Type' => $document->mime_type ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }

    public function download(Request $request, Team $current_team, PatientCase $case, PatientDocument $document): StreamedResponse
    {
        abort_unless($case->team_id === $current_team->id, 404);
        abort_unless($document->patient_case_id === $case->id, 404);
        Gate::authorize('download', $document);

        return Storage::disk($document->disk)->download(
            $document->path,
            $document->original_filename,
        );
    }
}
