<?php

namespace App\Jobs\Patients;

use App\Actions\Patients\MoveDocumentStorage;
use App\Models\PatientDocument;
use App\Models\Team;
use App\Support\Storage\OrganizationStorage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class MoveOrganizationDocuments implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3000;

    public int $uniqueFor = 3600;

    /**
     * @param  'to_external'|'to_platform'  $direction
     */
    public function __construct(public int $teamId, public string $direction) {}

    public function uniqueId(): string
    {
        return 'move-organization-documents:'.$this->teamId;
    }

    public function handle(MoveDocumentStorage $mover, OrganizationStorage $storage): void
    {
        $team = Team::query()->find($this->teamId);

        if ($team === null) {
            return;
        }

        $settings = $team->organizationSetting;
        $platformDisk = (string) config('careflow.documents.disk', 'local');

        if ($this->direction === 'to_external') {
            if ($settings === null || $settings->storage_driver === 'platform') {
                return;
            }

            $targetDisk = OrganizationStorage::EXTERNAL_PREFIX.$settings->storage_driver;
            $query = PatientDocument::query()->forTeam($team)->where('disk', $platformDisk);
        } else {
            $targetDisk = $platformDisk;
            $query = PatientDocument::query()->forTeam($team)->where('disk', 'like', OrganizationStorage::EXTERNAL_PREFIX.'%');
        }

        $failed = 0;
        $moved = 0;

        $query->orderBy('id')->each(function (PatientDocument $document) use ($mover, $targetDisk, &$failed, &$moved): void {
            try {
                $mover->handle($document, $targetDisk) && $moved++;
            } catch (Throwable $exception) {
                $failed++;
                Log::warning('Document storage move failed', [
                    'document_id' => $document->id,
                    'error' => class_basename($exception),
                ]);
            }
        });

        Log::info('Organization documents moved', ['team_id' => $team->id, 'direction' => $this->direction, 'moved' => $moved, 'failed' => $failed]);
    }
}
