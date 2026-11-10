<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\PatientDocument;
use App\Models\User;

class PatientDocumentPolicy
{
    public function view(User $user, PatientDocument $document): bool
    {
        return $user->can('view', $document->case)
            && $user->hasTeamPermission($document->team, TeamPermission::ViewDocuments);
    }

    public function download(User $user, PatientDocument $document): bool
    {
        return $this->view($user, $document);
    }

    public function reviewAi(User $user, PatientDocument $document): bool
    {
        return $user->can('view', $document->case)
            && $user->hasTeamPermission($document->team, TeamPermission::ReviewAiOutputs);
    }

    public function reprocess(User $user, PatientDocument $document): bool
    {
        return $user->can('view', $document->case)
            && $user->hasTeamPermission($document->team, TeamPermission::ManageDocuments);
    }
}
