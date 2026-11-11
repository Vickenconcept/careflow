<?php

namespace App\Ai\Tools\Assistant;

use App\Enums\TeamPermission;
use App\Models\PatientCase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;

/**
 * Shared plumbing for the assistant's tools.
 *
 * Every tool runs as the signed-in staff member inside one hospital, so it can
 * only see and change what that person could see and change in the app.
 */
abstract class AssistantTool implements Tool
{
    public function __construct(
        protected User $user,
        protected Team $team,
    ) {}

    /**
     * The name the model calls the tool by.
     */
    public function name(): string
    {
        return Str::snake(class_basename($this));
    }

    /**
     * A short label shown to people on approval cards and in the tool list.
     */
    abstract public static function label(): string;

    /**
     * The permission a person needs before the assistant is given this tool.
     */
    public static function requiredPermission(): ?TeamPermission
    {
        return TeamPermission::ViewCases;
    }

    /**
     * Cases this person is allowed to see in this hospital.
     *
     * @return Builder<PatientCase>
     */
    protected function visibleCases(): Builder
    {
        return PatientCase::query()->visibleToStaff($this->user, $this->team);
    }

    protected function findCase(string $reference): ?PatientCase
    {
        return $this->visibleCases()
            ->where('patient_cases.reference', strtoupper(trim($reference)))
            ->first();
    }

    protected function caseUrl(PatientCase $case, ?string $tab = null): string
    {
        return route('organizations.cases.show', array_filter([
            'current_team' => $this->team->slug,
            'case' => $case->reference,
            'tab' => $tab,
        ]), absolute: false);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
    }

    protected function notFound(string $reference): string
    {
        return $this->json([
            'error' => sprintf('No case %s is visible to you in %s.', strtoupper($reference), $this->team->name),
        ]);
    }
}
