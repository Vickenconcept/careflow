<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Patients\ConfirmDocumentReview;
use App\Actions\Patients\CorrectDocumentExtraction;
use App\Actions\Patients\SignOffDocumentTranslation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\CorrectDocumentExtractionRequest;
use App\Models\PatientCase;
use App\Models\PatientDocument;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use InvalidArgumentException;

class PatientDocumentReviewController extends Controller
{
    public function update(
        CorrectDocumentExtractionRequest $request,
        Team $current_team,
        PatientCase $case,
        PatientDocument $document,
        CorrectDocumentExtraction $correctDocumentExtraction,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);
        abort_unless($document->patient_case_id === $case->id, 404);

        try {
            $correctDocumentExtraction->handle(
                document: $document,
                actor: $request->user(),
                correctedPayload: $request->validated('corrected_payload'),
                correctedSections: $request->validated('corrected_sections'),
                reason: $request->validated('reason'),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors([
                'corrections' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Document review corrections saved. Original file was not modified.'),
        ]);

        return to_route('organizations.documents.show', [
            'current_team' => $current_team->slug,
            'case' => $case->reference,
            'document' => $document->uuid,
        ]);
    }

    public function signOffTranslation(
        Request $request,
        Team $current_team,
        PatientCase $case,
        PatientDocument $document,
        SignOffDocumentTranslation $signOffDocumentTranslation,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);
        abort_unless($document->patient_case_id === $case->id, 404);
        Gate::authorize('reviewAi', $document);

        try {
            $signOffDocumentTranslation->handle($document, $request->user(), $request->string('note')->toString() ?: null);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['corrections' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Translation signed off.')]);

        return back();
    }

    public function confirm(
        Request $request,
        Team $current_team,
        PatientCase $case,
        PatientDocument $document,
        ConfirmDocumentReview $confirmDocumentReview,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);
        abort_unless($document->patient_case_id === $case->id, 404);
        Gate::authorize('reviewAi', $document);

        try {
            $confirmDocumentReview->handle($document, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['corrections' => $exception->getMessage()]);
        }

        $nextPending = $case->documents()
            ->whereKeyNot($document->id)
            ->with(['currentExtraction', 'currentSummary'])
            ->orderBy('uploaded_at')
            ->get()
            ->first(fn (PatientDocument $other) => $other->currentExtraction?->review_status === 'unreviewed'
                || $other->currentSummary?->review_status === 'unreviewed');

        if ($nextPending !== null) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Marked as reviewed. Next document to review.'),
            ]);

            return to_route('organizations.documents.show', [
                'current_team' => $current_team->slug,
                'case' => $case->reference,
                'document' => $nextPending->uuid,
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('All documents reviewed.'),
        ]);

        return redirect(route('organizations.cases.show', [
            'current_team' => $current_team->slug,
            'case' => $case->reference,
        ]).'?tab=documents');
    }
}
