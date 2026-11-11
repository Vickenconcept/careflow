<?php

namespace App\Ai\Tools\Assistant;

use App\Queries\OrganizationDashboardMetricsQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class WorkloadSummary extends AssistantTool
{
    public static function label(): string
    {
        return 'Pipeline overview';
    }

    public function description(): Stringable|string
    {
        return 'Get the hospital pipeline as the user is allowed to see it: open cases, backlogs waiting on coordinators, doctors or patients, documents needing review, overdue follow-ups, conversion rates and workload per coordinator.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Stringable|string
    {
        $metrics = app(OrganizationDashboardMetricsQuery::class)->for($this->user, $this->team);

        if (! ($metrics['available'] ?? false)) {
            return $this->json(['error' => 'Pipeline numbers are not available for your role.']);
        }

        return $this->json([
            'open_cases' => $metrics['open_cases'] ?? null,
            'awaiting_coordinator_review' => $metrics['awaiting_coordinator_review'] ?? null,
            'awaiting_doctor_review' => $metrics['awaiting_doctor_review'] ?? null,
            'documents_needing_review' => $metrics['documents_needing_review'] ?? null,
            'pipeline' => $metrics['executive'] ?? null,
            'performance' => $metrics['operational'] ?? null,
            'workload_by_coordinator' => $metrics['workload_by_coordinator'] ?? [],
            'includes_demo_cases' => $metrics['demo_cases'] ?? 0,
        ]);
    }
}
