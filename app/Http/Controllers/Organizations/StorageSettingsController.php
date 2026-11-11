<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Jobs\Patients\MoveOrganizationDocuments;
use App\Models\PatientDocument;
use App\Models\Team;
use App\Support\Storage\OrganizationStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Throwable;

class StorageSettingsController extends Controller
{
    public function edit(Team $current_team, OrganizationStorage $storage): Response
    {
        Gate::authorize('manageSettings', $current_team);

        $settings = $current_team->ensureOrganizationSetting();
        $config = $settings->storage_config ?? [];

        return Inertia::render('organizations/storage', [
            'team' => ['id' => $current_team->id, 'name' => $current_team->name, 'slug' => $current_team->slug],
            'driver' => $settings->storage_driver,
            'driverOptions' => collect(OrganizationStorage::drivers())
                ->map(fn (string $label, string $value) => compact('value', 'label'))->values(),
            'config' => [
                'key_hint' => isset($config['key']) ? '…'.substr((string) $config['key'], -4) : null,
                'region' => $config['region'] ?? '',
                'bucket' => $config['bucket'] ?? '',
                'endpoint' => $config['endpoint'] ?? '',
                'use_path_style_endpoint' => (bool) ($config['use_path_style_endpoint'] ?? false),
                'cloud_name' => $config['cloud_name'] ?? '',
                'api_key_hint' => isset($config['api_key']) ? '…'.substr((string) $config['api_key'], -4) : null,
                'has_secret' => ! empty($config['secret']) || ! empty($config['api_secret']),
            ],
            'externalDocuments' => $storage->hasExternalDocuments($current_team),
            'documentCounts' => [
                'platform' => PatientDocument::query()->forTeam($current_team)->where('disk', config('careflow.documents.disk', 'local'))->count(),
                'external' => PatientDocument::query()->forTeam($current_team)->where('disk', 'like', OrganizationStorage::EXTERNAL_PREFIX.'%')->count(),
            ],
        ]);
    }

    public function move(Request $request, Team $current_team): RedirectResponse
    {
        Gate::authorize('manageSettings', $current_team);

        $direction = $request->validate(['direction' => ['required', Rule::in(['to_external', 'to_platform'])]])['direction'];
        $settings = $current_team->ensureOrganizationSetting();

        if ($direction === 'to_external' && $settings->storage_driver === 'platform') {
            return back()->withErrors(['driver' => 'Connect your own storage first.']);
        }

        MoveOrganizationDocuments::dispatch($current_team->id, $direction);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Moving documents in the background. Refresh to see progress.')]);

        return back();
    }

    public function update(Request $request, Team $current_team, OrganizationStorage $storage): RedirectResponse
    {
        Gate::authorize('manageSettings', $current_team);

        $settings = $current_team->ensureOrganizationSetting();
        $previous = $settings->storage_config ?? [];

        $validated = $request->validate([
            'driver' => ['required', Rule::in(array_keys(OrganizationStorage::drivers()))],
            'key' => ['nullable', 'string', 'max:200'],
            'secret' => ['nullable', 'string', 'max:400'],
            'region' => ['nullable', 'string', 'max:60'],
            'bucket' => ['nullable', 'string', 'max:120'],
            'endpoint' => ['nullable', 'url', 'max:300'],
            'use_path_style_endpoint' => ['sometimes', 'boolean'],
            'cloud_name' => ['nullable', 'string', 'max:120'],
            'api_key' => ['nullable', 'string', 'max:200'],
            'api_secret' => ['nullable', 'string', 'max:400'],
        ]);

        $driver = $validated['driver'];
        $sameDriver = $settings->storage_driver === $driver;

        if ($driver === 'platform') {
            if ($settings->storage_driver !== 'platform' && $storage->hasExternalDocuments($current_team)) {
                return back()->withErrors(['driver' => 'Documents are still stored in your own storage. Keep it connected, or export and delete them first.']);
            }

            $settings->update(['storage_driver' => 'platform', 'storage_config' => null]);

            return $this->saved();
        }

        $config = $driver === 's3'
            ? [
                'key' => $validated['key'] ?? null ?: ($sameDriver ? ($previous['key'] ?? '') : ''),
                'secret' => $validated['secret'] ?? null ?: ($sameDriver ? ($previous['secret'] ?? '') : ''),
                'region' => ($validated['region'] ?? null) ?: 'us-east-1',
                'bucket' => $validated['bucket'] ?? '',
                'endpoint' => $validated['endpoint'] ?? '',
                'use_path_style_endpoint' => (bool) ($validated['use_path_style_endpoint'] ?? false),
            ]
            : [
                'cloud_name' => $validated['cloud_name'] ?? '',
                'api_key' => $validated['api_key'] ?? null ?: ($sameDriver ? ($previous['api_key'] ?? '') : ''),
                'api_secret' => $validated['api_secret'] ?? null ?: ($sameDriver ? ($previous['api_secret'] ?? '') : ''),
            ];

        $required = $driver === 's3' ? ['key', 'secret', 'bucket'] : ['cloud_name', 'api_key', 'api_secret'];

        foreach ($required as $field) {
            if (($config[$field] ?? '') === '') {
                return back()->withErrors([$field => 'This field is required.']);
            }
        }

        if ($storage->hasExternalDocuments($current_team) && $this->targetChanged($driver, $previous, $config, $sameDriver)) {
            return back()->withErrors(['driver' => 'Existing documents live in your current bucket or account. Changing the target would orphan them, so it is blocked.']);
        }

        try {
            $storage->assertPublicEndpoint($config['endpoint'] ?? null);
            $storage->probe($storage->build($driver, $config));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['endpoint' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            Log::warning('Organization storage connection test failed', [
                'team_id' => $current_team->id,
                'driver' => $driver,
                'error' => class_basename($exception),
            ]);

            return back()->withErrors(['driver' => 'Could not write to that storage. Check the credentials, bucket name and permissions, then try again.']);
        }

        $settings->update(['storage_driver' => $driver, 'storage_config' => $config]);

        return $this->saved();
    }

    /**
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $config
     */
    protected function targetChanged(string $driver, array $previous, array $config, bool $sameDriver): bool
    {
        if (! $sameDriver) {
            return true;
        }

        $fields = $driver === 's3' ? ['bucket', 'endpoint', 'region'] : ['cloud_name'];

        foreach ($fields as $field) {
            if (($previous[$field] ?? '') !== ($config[$field] ?? '')) {
                return true;
            }
        }

        return false;
    }

    protected function saved(): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document storage saved. New uploads will use it.')]);

        return back();
    }
}
