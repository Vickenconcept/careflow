<?php

namespace App\Listeners\Patients;

use App\Enums\TeamRole;
use App\Events\Patients\DocumentProcessingCompleted;
use App\Models\PatientCase;
use App\Models\PatientDocument;
use App\Notifications\Patients\DocumentReadyNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendDocumentReadyNotifications implements ShouldQueue
{
    public function handle(DocumentProcessingCompleted $event): void
    {
        $case = PatientCase::query()
            ->with(['patient', 'team.organizationSetting', 'assignedCoordinator:id,name,email'])
            ->find($event->caseId);

        $document = PatientDocument::query()->find($event->documentId);

        if ($case === null || $document === null) {
            return;
        }

        $recipients = collect();

        if ($case->assignedCoordinator !== null) {
            $recipients->push($case->assignedCoordinator);
        } else {
            $recipients = $recipients->merge(
                $case->team->members()
                    ->wherePivotIn('role', [
                        TeamRole::Owner->value,
                        TeamRole::Admin->value,
                        TeamRole::Coordinator->value,
                    ])
                    ->get()
            );
        }

        Notification::send(
            $recipients->unique('id')->values(),
            new DocumentReadyNotification($case, $document),
        );
    }
}
