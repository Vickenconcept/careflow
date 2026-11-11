<?php

namespace Tests;

use App\Contracts\Documents\DocumentClassifier;
use App\Contracts\Documents\DocumentLanguageDetector;
use App\Contracts\Documents\DocumentSummarizer;
use App\Contracts\Documents\DocumentTextExtractor;
use App\Contracts\Documents\DocumentTranslator;
use App\Contracts\Documents\MedicalInformationExtractor;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Tests\Support\Ai\MockDocumentClassifier;
use Tests\Support\Ai\MockDocumentLanguageDetector;
use Tests\Support\Ai\MockDocumentSummarizer;
use Tests\Support\Ai\MockDocumentTextExtractor;
use Tests\Support\Ai\MockDocumentTranslator;
use Tests\Support\Ai\MockMedicalInformationExtractor;

abstract class TestCase extends BaseTestCase
{
    /**
     * Automated tests never call the live AI provider: swap in deterministic doubles
     * that only exist in the test suite. Tests that exercise the OpenAI adapters
     * resolve those classes directly with a faked HTTP layer.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(DocumentTextExtractor::class, MockDocumentTextExtractor::class);
        $this->app->bind(DocumentLanguageDetector::class, MockDocumentLanguageDetector::class);
        $this->app->bind(DocumentClassifier::class, MockDocumentClassifier::class);
        $this->app->bind(MedicalInformationExtractor::class, MockMedicalInformationExtractor::class);
        $this->app->bind(DocumentTranslator::class, MockDocumentTranslator::class);
        $this->app->bind(DocumentSummarizer::class, MockDocumentSummarizer::class);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
