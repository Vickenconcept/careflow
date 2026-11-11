<?php

namespace App\Support\Storage;

use App\Models\OrganizationSetting;
use App\Models\PatientDocument;
use App\Models\Team;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Decides where an organization's patient documents live: on the platform's
 * private disk, or on storage the hospital owns (S3-compatible bucket or Cloudinary).
 */
class OrganizationStorage
{
    public const EXTERNAL_PREFIX = 'external:';

    /**
     * @return array<string, string>
     */
    public static function drivers(): array
    {
        return [
            'platform' => 'CareFlow private storage',
            's3' => 'Your own S3-compatible bucket (AWS S3, Cloudflare R2, MinIO, Wasabi…)',
            'cloudinary' => 'Your own Cloudinary account (private assets)',
        ];
    }

    /**
     * Disk name recorded on every new document for this organization.
     */
    public function diskNameForNewUploads(Team $team): string
    {
        $driver = $team->organizationSetting?->storage_driver ?? 'platform';

        return $driver === 'platform'
            ? (string) config('careflow.documents.disk', 'local')
            : self::EXTERNAL_PREFIX.$driver;
    }

    /**
     * Resolve the filesystem that holds a stored document.
     */
    public function filesystemFor(Team $team, string $diskName): Filesystem
    {
        if (! str_starts_with($diskName, self::EXTERNAL_PREFIX)) {
            return Storage::disk($diskName);
        }

        $driver = Str::after($diskName, self::EXTERNAL_PREFIX);
        $settings = $team->organizationSetting;

        if ($settings === null || $settings->storage_driver !== $driver || empty($settings->storage_config)) {
            throw new RuntimeException('The storage this document was saved to is no longer configured for the organization.');
        }

        return $this->build($driver, $settings->storage_config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function build(string $driver, array $config): Filesystem
    {
        return match ($driver) {
            's3' => Storage::build([
                'driver' => 's3',
                'key' => $config['key'] ?? '',
                'secret' => $config['secret'] ?? '',
                'region' => $config['region'] ?? 'us-east-1',
                'bucket' => $config['bucket'] ?? '',
                'endpoint' => ($config['endpoint'] ?? '') ?: null,
                'use_path_style_endpoint' => (bool) ($config['use_path_style_endpoint'] ?? false),
                'visibility' => 'private',
                'throw' => true,
            ]),
            'cloudinary' => Storage::build([
                'driver' => 'cloudinary',
                'cloud_name' => $config['cloud_name'] ?? '',
                'api_key' => $config['api_key'] ?? '',
                'api_secret' => $config['api_secret'] ?? '',
                'throw' => true,
            ]),
            default => throw new InvalidArgumentException('Unsupported storage driver.'),
        };
    }

    /**
     * Prove the credentials work by writing, reading back and deleting a tiny file.
     */
    public function probe(Filesystem $filesystem): void
    {
        $path = 'careflow-connection-test/'.Str::uuid().'.txt';
        $contents = 'careflow-connection-test';

        $filesystem->put($path, $contents);

        try {
            if ($filesystem->get($path) !== $contents) {
                throw new RuntimeException('The storage returned different content than was written.');
            }
        } finally {
            $filesystem->delete($path);
        }
    }

    /**
     * Refuse endpoints that point at the platform's own network (SSRF protection).
     */
    public function assertPublicEndpoint(?string $endpoint): void
    {
        if ($endpoint === null || $endpoint === '') {
            return;
        }

        $parts = parse_url($endpoint);
        $host = $parts['host'] ?? null;

        if (($parts['scheme'] ?? null) !== 'https' || $host === null) {
            throw new InvalidArgumentException('The endpoint must be an https URL.');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if ($addresses === []) {
            throw new InvalidArgumentException('The endpoint host could not be resolved.');
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new InvalidArgumentException('The endpoint must be a public internet address.');
            }
        }
    }

    /**
     * Whether any stored document still depends on the organization's external storage.
     */
    public function hasExternalDocuments(Team $team): bool
    {
        return PatientDocument::query()
            ->forTeam($team)
            ->where('disk', 'like', self::EXTERNAL_PREFIX.'%')
            ->exists();
    }

    public static function describe(OrganizationSetting $settings): ?string
    {
        $config = $settings->storage_config ?? [];

        return match ($settings->storage_driver) {
            's3' => ($config['bucket'] ?? '').' · '.($config['region'] ?? ''),
            'cloudinary' => $config['cloud_name'] ?? null,
            default => null,
        };
    }
}
