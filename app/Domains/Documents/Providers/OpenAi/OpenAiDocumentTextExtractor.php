<?php

namespace App\Domains\Documents\Providers\OpenAi;

use App\Ai\Agents\DocumentVisionTextExtractionAgent;
use App\Contracts\Documents\DocumentTextExtractor;
use App\Domains\Documents\Data\ExtractedTextResult;
use App\Models\PatientDocument;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Files\Document as AiDocument;
use Laravel\Ai\Files\Image as AiImage;
use RuntimeException;

/**
 * Text extraction for the OpenAI/Laravel AI pipeline.
 *
 * Readable UTF-8 text files are used directly. Images use Laravel AI vision
 * attachments. PDFs are always sent as Laravel AI document files, even when
 * uncompressed, so raw PDF syntax is never treated as the document's text.
 */
class OpenAiDocumentTextExtractor implements DocumentTextExtractor
{
    public function extract(PatientDocument $document): ExtractedTextResult
    {
        if (! $document->filesystem()->exists($document->path)) {
            throw new RuntimeException('Stored file is missing from private storage.');
        }

        $extension = strtolower($document->extension);
        $bytes = $document->filesystem()->get($document->path);

        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('Document file is empty.');
        }

        $isBinaryFormat = in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif'], true);

        if (! $isBinaryFormat && ! str_contains($bytes, "\0") && preg_match('//u', $bytes) === 1) {
            $text = trim($bytes);

            if ($text !== '') {
                if (strlen($text) > 100000) {
                    $text = substr($text, 0, 100000);

                    return new ExtractedTextResult(
                        text: $text,
                        provider: 'openai',
                        isSynthetic: false,
                        warnings: ['Extracted text truncated to 100000 characters for AI processing.'],
                    );
                }

                return new ExtractedTextResult(
                    text: $text,
                    provider: 'openai',
                    isSynthetic: false,
                    warnings: [],
                );
            }
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return $document->withLocalCopy(fn (string $localPath) => $this->extractViaVision($document, $localPath));
        }

        if ($extension === 'pdf') {
            return $document->withLocalCopy(fn (string $localPath) => $this->extractViaDocumentAttachment($document, $localPath));
        }

        throw new RuntimeException(
            'Unable to extract text from this document type with the current Laravel AI provider configuration.',
        );
    }

    protected function extractViaVision(PatientDocument $document, string $absolutePath): ExtractedTextResult
    {
        $response = (new DocumentVisionTextExtractionAgent)->prompt(
            prompt: 'Extract all readable text from this medical document image. Filename: '.$document->original_filename,
            attachments: [
                AiImage::fromPath($absolutePath, $document->mime_type),
            ],
            provider: Lab::OpenAI,
        );

        return $this->resultFromStructuredExtraction($response);
    }

    protected function extractViaDocumentAttachment(PatientDocument $document, string $absolutePath): ExtractedTextResult
    {
        $response = (new DocumentVisionTextExtractionAgent)->prompt(
            prompt: 'Extract all readable text from this medical PDF document. Filename: '.$document->original_filename,
            attachments: [
                AiDocument::fromPath($absolutePath),
            ],
            provider: Lab::OpenAI,
        );

        return $this->resultFromStructuredExtraction($response);
    }

    /**
     * @param  array<string, mixed>|\ArrayAccess<string, mixed>  $response
     */
    protected function resultFromStructuredExtraction(mixed $response): ExtractedTextResult
    {
        $text = trim((string) ($response['extracted_text'] ?? ''));
        $warnings = collect($response['warnings'] ?? [])
            ->filter(fn ($warning) => is_string($warning) && trim($warning) !== '')
            ->map(fn (string $warning) => trim($warning))
            ->values()
            ->all();

        if ((bool) ($response['is_partial'] ?? false)) {
            $warnings[] = 'Vision extraction reported partial readability.';
        }

        if ($text === '') {
            throw new RuntimeException('Laravel AI vision extraction returned no readable text.');
        }

        return new ExtractedTextResult(
            text: $text,
            provider: 'openai',
            isSynthetic: false,
            warnings: $warnings,
        );
    }
}
