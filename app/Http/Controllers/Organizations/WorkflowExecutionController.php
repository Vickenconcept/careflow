<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\WorkflowExecution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowExecutionController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('view', $current_team);

        $executions = WorkflowExecution::query()
            ->where('team_id', $current_team->id)
            ->with(['definition:id,key,name', 'case:id,reference'])
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (WorkflowExecution $execution) => [
                'id' => $execution->id,
                'uuid' => $execution->uuid,
                'status' => $execution->status->value,
                'status_label' => $execution->status->label(),
                'idempotency_key' => $execution->idempotency_key,
                'started_at' => $execution->started_at?->toIso8601String(),
                'finished_at' => $execution->finished_at?->toIso8601String(),
                'workflow' => $execution->definition?->name
                    ?? $execution->definition?->key?->value
                    ?? $execution->workflow_key->value,
                'case_reference' => $execution->case?->reference,
                'error_message' => $execution->error_message,
            ]);

        return Inertia::render('organizations/workflows/index', [
            'team' => [
                'id' => $current_team->id,
                'name' => $current_team->name,
                'slug' => $current_team->slug,
            ],
            'executions' => $executions,
        ]);
    }
}
