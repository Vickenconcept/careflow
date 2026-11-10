<?php

namespace App\Actions\Patients;

use App\Enums\DocumentProcessingStatus;
use App\Jobs\Patients\ProcessPatientDocument;
use App\Models\PatientDocument;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class QueuePatientDocumentForProcessing
{
    public function __construct(private CacheRepository $cache) {}

    /**
     * Reset a document to pending and dispatch the background AI pipeline job.
     */
    public function handle(PatientDocument $document): PatientDocument
    {
        $document->update([
            'processing_status' => DocumentProcessingStatus::Pending,
            'processing_error' => null,
        ]);

        $job = new ProcessPatientDocument($document->id);

        // Staff reprocess / stuck recovery must not wait up to uniqueFor seconds
        // for a previous failed attempt's cache lock to expire.
        (new UniqueLock($this->cache))->release($job);

        ProcessPatientDocument::dispatch($document->id);

        return $document->fresh();
    }
}
