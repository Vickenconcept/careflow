<?php

namespace App\Ai\Tools\Assistant;

use App\Models\User;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Request;

/**
 * A tool that changes data. The assistant may propose it, but it only runs
 * after the person approves the exact action shown on the approval card.
 */
abstract class ApprovalRequiredTool extends AssistantTool implements Approvable
{
    use InteractsWithApprovals;

    /**
     * One plain sentence describing exactly what will happen if approved.
     */
    abstract public function describe(Request $request): string;

    protected function needsApproval(Request $request): Approval|bool
    {
        return Approval::required($this->describe($request));
    }

    protected function memberName(mixed $userId): ?string
    {
        if (! is_numeric($userId)) {
            return null;
        }

        return $this->team->members()->whereKey((int) $userId)->value('name');
    }

    protected function member(mixed $userId): ?User
    {
        if (! is_numeric($userId)) {
            return null;
        }

        return $this->team->members()->whereKey((int) $userId)->first();
    }
}
