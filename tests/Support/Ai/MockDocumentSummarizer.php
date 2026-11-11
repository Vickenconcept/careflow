<?php

namespace Tests\Support\Ai;

use App\Contracts\Documents\DocumentSummarizer;
use App\Domains\Documents\Data\StructuredExtractionResult;
use App\Domains\Documents\Data\SummaryResult;
use App\Models\PatientDocument;

class MockDocumentSummarizer implements DocumentSummarizer
{
    public function summarize(
        PatientDocument $document,
        string $text,
        StructuredExtractionResult $extraction,
    ): SummaryResult {
        return new SummaryResult(
            sections: [
                'document_overview' => 'Mock AI summary for '.$document->original_filename.'. Demonstration only.',
                'document_date' => null,
                'reported_diagnosis' => $extraction->payload['diagnoses_as_documented'] ?? [],
                'relevant_documented_history' => [],
                'reported_symptoms' => [],
                'medications_listed' => [],
                'allergies_listed' => [],
                'laboratory_findings' => [],
                'imaging_findings_as_written' => [],
                'procedures_or_treatments_documented' => [],
                'missing_or_unclear_information' => $extraction->payload['missing_or_unreadable_information'] ?? [],
                'source_document_references' => $extraction->payload['source_references'] ?? [],
                'facts_explicitly_found' => [],
                'uncertain_or_ambiguous_content' => $extraction->payload['uncertain_sections'] ?? [],
            ],
            provider: 'mock',
            isSynthetic: true,
        );
    }
}
