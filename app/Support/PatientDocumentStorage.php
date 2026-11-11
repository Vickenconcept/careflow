<?php

namespace App\Support;

use App\Models\PatientCase;
use App\Models\Team;
use App\Support\Storage\OrganizationStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class PatientDocumentStorage
{
    public function __construct(private OrganizationStorage $organizationStorage) {}

    /**
     * Store an uploaded patient document on the organization's private storage.
     *
     * @return array{disk: string, path: string, uuid: string, mime_type: string, extension: string, size_bytes: int, checksum_sha256: string|null, original_filename: string}
     */
    public function store(Team $team, PatientCase $case, UploadedFile $file): array
    {
        $disk = $this->organizationStorage->diskNameForNewUploads($team);
        $uuid = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $directory = sprintf('teams/%d/cases/%d/documents', $team->id, $case->id);
        $path = $directory.'/'.$uuid.'.'.$extension;

        $filesystem = $this->organizationStorage->filesystemFor($team, $disk);
        $stream = fopen($file->getRealPath(), 'rb');

        try {
            $stored = $filesystem->put($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($stored === false) {
            throw new RuntimeException('Unable to store patient document.');
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'uuid' => $uuid,
            'mime_type' => (string) ($file->getMimeType() ?: 'application/octet-stream'),
            'extension' => $extension,
            'size_bytes' => (int) $file->getSize(),
            'checksum_sha256' => hash_file('sha256', $file->getRealPath()) ?: null,
            'original_filename' => $file->getClientOriginalName(),
        ];
    }
}
