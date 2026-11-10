<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Patients\CorrectDocumentExtraction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\CorrectDocumentExtractionRequest;
use App\Models\PatientCase;
use App\Models\PatientDocument;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
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
}
