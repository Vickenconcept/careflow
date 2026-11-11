<?php

namespace App\Support\Storage;

use Illuminate\Support\Facades\Http;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;

/**
 * Minimal Flysystem adapter that keeps files on Cloudinary as private
 * ("authenticated") raw assets. Files are never reachable by a public URL:
 * reads go through signed, short-lived API download requests made server side.
 */
class CloudinaryAdapter implements FilesystemAdapter
{
    public function __construct(
        private string $cloudName,
        private string $apiKey,
        private string $apiSecret,
    ) {}

    public function fileExists(string $path): bool
    {
        $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
            ->get($this->api('resources/raw/authenticated/'.$this->encodePath($path)));

        return $response->successful();
    }

    public function directoryExists(string $path): bool
    {
        return false;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $params = $this->signed([
            'public_id' => $path,
            'type' => 'authenticated',
            'overwrite' => 'true',
        ]);

        $response = Http::asMultipart()
            ->attach('file', $contents, basename($path))
            ->post($this->api('raw/upload'), $this->flatten($params));

        if (! $response->successful()) {
            throw UnableToWriteFile::atLocation($path, (string) $response->body());
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->write($path, (string) stream_get_contents($contents), $config);
    }

    public function read(string $path): string
    {
        $params = $this->signed([
            'public_id' => $path,
            'type' => 'authenticated',
        ]);

        $response = Http::get($this->api('raw/download'), $params);

        if (! $response->successful()) {
            throw UnableToReadFile::fromLocation($path, (string) $response->status());
        }

        return $response->body();
    }

    public function readStream(string $path)
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $this->read($path));
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        $params = $this->signed([
            'public_id' => $path,
            'type' => 'authenticated',
            'invalidate' => 'true',
        ]);

        $response = Http::asForm()->post($this->api('raw/destroy'), $params);

        if (! $response->successful()) {
            throw UnableToDeleteFile::atLocation($path, (string) $response->body());
        }
    }

    public function deleteDirectory(string $path): void
    {
        throw UnableToDeleteDirectory::atLocation($path, 'Directories are not supported.');
    }

    public function createDirectory(string $path, Config $config): void
    {
        throw UnableToCreateDirectory::atLocation($path, 'Directories are not supported.');
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, 'Files are always private.');
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, null, 'private');
    }

    public function mimeType(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::mimeType($path, 'Not supported.');
    }

    public function lastModified(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::lastModified($path, 'Not supported.');
    }

    public function fileSize(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::fileSize($path, 'Not supported.');
    }

    public function listContents(string $path, bool $deep): iterable
    {
        return [];
    }

    public function move(string $source, string $destination, Config $config): void
    {
        throw UnableToMoveFile::fromLocationTo($source, $destination);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        throw UnableToCopyFile::fromLocationTo($source, $destination);
    }

    /**
     * @param  array<string, string>  $params
     * @return array<string, string>
     */
    public function signed(array $params): array
    {
        $params['timestamp'] = (string) time();
        $toSign = $params;
        ksort($toSign);

        $params['signature'] = sha1(urldecode(http_build_query($toSign)).$this->apiSecret);
        $params['api_key'] = $this->apiKey;

        return $params;
    }

    /**
     * @param  array<string, string>  $params
     * @return list<array{name: string, contents: string}>
     */
    private function flatten(array $params): array
    {
        return collect($params)->map(fn ($value, $name) => ['name' => (string) $name, 'contents' => (string) $value])->values()->all();
    }

    private function api(string $endpoint): string
    {
        return 'https://api.cloudinary.com/v1_1/'.rawurlencode($this->cloudName).'/'.$endpoint;
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
