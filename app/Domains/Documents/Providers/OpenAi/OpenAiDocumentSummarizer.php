<?php

namespace App\Domains\Documents\Providers\OpenAi;

use App\Ai\Agents\DocumentSummaryAgent;
use App\Contracts\Documents\DocumentSummarizer;
use App\Domains\Documents\Data\StructuredExtractionResult;
use App\Domains\Documents\Data\SummaryResult;
use App\Models\PatientDocument;
use Laravel\Ai\Enums\Lab;

class OpenAiDocumentSummarizer implements DocumentSummarizer
{
    public function summarize(
        PatientDocument $document,
        string $text,
        StructuredExtractionResult $extraction,
    ): SummaryResult {
        $response = (new DocumentSummaryAgent)->prompt(
            prompt: implode("\n\n", [
                'Filename: '.$document->original_filename,
                'Structured extraction JSON:',
                json_encode($extraction->payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}',
                'Source text:',
                $this->truncate($text),
            ]),
            provider: Lab::OpenAI,
        );

        return new SummaryResult(
            sections: [
                'document_overview' => (string) ($response['document_overview'] ?? ''),
                'document_date' => $response['document_date'] ?? null,
                'reported_diagnosis' => $this->stringList($response['reported_diagnosis'] ?? []),
                'relevant_documented_history' => $this->stringList($response['relevant_documented_history'] ?? []),
                'reported_symptoms' => $this->stringList($response['reported_symptoms'] ?? []),
                'medications_listed' => $this->stringList($response['medications_listed'] ?? []),
                'allergies_listed' => $this->stringList($response['allergies_listed'] ?? []),
                'laboratory_findings' => $this->stringList($response['laboratory_findings'] ?? []),
                'imaging_findings_as_written' => $this->stringList($response['imaging_findings_as_written'] ?? []),
                'procedures_or_treatments_documented' => $this->stringList($response['procedures_or_treatments_documented'] ?? []),
                'missing_or_unclear_information' => $this->stringList($response['missing_or_unclear_information'] ?? []),
                'source_document_references' => $this->stringList($response['source_document_references'] ?? []),
                'facts_explicitly_found' => $this->stringList($response['facts_explicitly_found'] ?? []),
                'uncertain_or_ambiguous_content' => $this->stringList($response['uncertain_or_ambiguous_content'] ?? []),
            ],
            provider: 'openai',
            isSynthetic: false,
        );
    }

    protected function truncate(string $text): string
    {
        return mb_strlen($text) > 10000 ? mb_substr($text, 0, 10000) : $text;
    }

    /**
     * @return list<string>
     */
    protected function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->map(fn (string $item) => trim($item))
            ->values()
            ->all();
    }
}
