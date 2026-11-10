<?php

namespace App\Support;

use App\Models\PatientCase;
use App\Models\Team;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PatientDocumentStorage
{
    /**
     * Store an uploaded patient document on the private disk.
     *
     * @return array{disk: string, path: string, uuid: string, mime_type: string, extension: string, size_bytes: int, checksum_sha256: string|null, original_filename: string}
     */
    public function store(Team $team, PatientCase $case, UploadedFile $file): array
    {
        $disk = (string) config('careflow.documents.disk', 'local');
        $uuid = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $directory = sprintf('teams/%d/cases/%d/documents', $team->id, $case->id);
        $filename = $uuid.'.'.$extension;

        $path = $file->storeAs($directory, $filename, $disk);

        if ($path === false) {
            throw new RuntimeException('Unable to store patient document.');
        }

        $absolutePath = Storage::disk($disk)->path($path);

        return [
            'disk' => $disk,
            'path' => $path,
            'uuid' => $uuid,
            'mime_type' => (string) ($file->getMimeType() ?: 'application/octet-stream'),
            'extension' => $extension,
            'size_bytes' => (int) $file->getSize(),
            'checksum_sha256' => hash_file('sha256', $absolutePath) ?: null,
            'original_filename' => $file->getClientOriginalName(),
        ];
    }
}
