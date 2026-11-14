<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Patients\AddCaseDocuments;
use App\Enums\DocumentCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\StoreCaseDocumentsRequest;
use App\Models\PatientCase;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;

class CaseDocumentUploadController extends Controller
{
    /**
     * Add files the patient sent outside the portal (email, WhatsApp, a USB stick) to the case.
     */
    public function __invoke(
        StoreCaseDocumentsRequest $request,
        Team $current_team,
        PatientCase $case,
        AddCaseDocuments $addCaseDocuments,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);

        $category = DocumentCategory::tryFrom((string) $request->input('category')) ?? DocumentCategory::OtherSupportingDocument;

        $documents = $addCaseDocuments->handle(
            $case,
            collect($request->file('documents', []))
                ->values()
                ->map(fn (UploadedFile $file) => ['file' => $file, 'category' => $category])
                ->all(),
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice('{1} Document added.|[2,*] :count documents added.', count($documents), ['count' => count($documents)]),
        ]);

        return to_route('organizations.cases.show', [
            'current_team' => $current_team->slug,
            'case' => $case->reference,
            'tab' => 'documents',
        ]);
    }
}
