<?php

namespace App\Actions\Patients;

use App\Models\PatientDocument;
use App\Support\Storage\OrganizationStorage;
use RuntimeException;
use Throwable;

class MoveDocumentStorage
{
    public function __construct(private OrganizationStorage $organizationStorage) {}

    /**
     * Copy a stored document to another storage location, verify it, repoint the record,
     * and only then remove the original. A failure at any step leaves the original untouched.
     */
    public function handle(PatientDocument $document, string $targetDisk): bool
    {
        if ($document->disk === $targetDisk) {
            return false;
        }

        $source = $document->filesystem();
        $target = $this->organizationStorage->filesystemFor($document->team, $targetDisk);

        $contents = $source->get($document->path);

        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('The source file is missing or empty.');
        }

        if ($document->checksum_sha256 !== null && ! hash_equals($document->checksum_sha256, hash('sha256', $contents))) {
            throw new RuntimeException('The source file does not match its recorded checksum.');
        }

        $target->put($document->path, $contents);

        if (hash('sha256', (string) $target->get($document->path)) !== hash('sha256', $contents)) {
            $target->delete($document->path);

            throw new RuntimeException('The copy could not be verified.');
        }

        $document->update(['disk' => $targetDisk]);

        try {
            $source->delete($document->path);
        } catch (Throwable) {
            // The record already points at the verified copy, so a leftover original is harmless.
        }

        return true;
    }
}
