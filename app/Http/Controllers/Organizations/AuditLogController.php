<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Models\CaseActivity;
use App\Models\DocumentAccessLog;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function __invoke(Request $request, Team $current_team): Response
    {
        Gate::authorize('manageSettings', $current_team);

        $view = $request->query('view') === 'activity' ? 'activity' : 'documents';

        $documentAccess = $view === 'documents'
            ? DocumentAccessLog::query()
                ->forTeam($current_team)
                ->with('user:id,name')
                ->latest('accessed_at')
                ->latest('id')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (DocumentAccessLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user' => $log->user?->name ?? 'Deleted user',
                    'document' => $log->document_filename,
                    'case_reference' => $log->case_reference,
                    'ip_address' => $log->ip_address,
                    'accessed_at' => $log->accessed_at->toIso8601String(),
                ])
            : null;

        $activity = $view === 'activity'
            ? CaseActivity::query()
                ->forTeam($current_team)
                ->with(['actor:id,name', 'case:id,reference'])
                ->latest('occurred_at')
                ->latest('id')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (CaseActivity $item) => [
                    'id' => $item->id,
                    'type' => $item->activity_type->label(),
                    'summary' => $item->summary,
                    'actor' => $item->actor?->name ?? 'System or patient',
                    'case_reference' => $item->case?->reference,
                    'patient_visible' => $item->is_patient_visible,
                    'occurred_at' => $item->occurred_at->toIso8601String(),
                ])
            : null;

        return Inertia::render('organizations/audit', [
            'team' => [
                'id' => $current_team->id,
                'name' => $current_team->name,
                'slug' => $current_team->slug,
            ],
            'view' => $view,
            'documentAccess' => $documentAccess,
            'activity' => $activity,
        ]);
    }
}
