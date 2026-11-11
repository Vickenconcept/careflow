<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\UpdateOrganizationPreferencesRequest;
use App\Models\OrganizationSetting;
use App\Models\Team;
use App\Models\WorkflowDefinition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationPreferencesController extends Controller
{
    public function edit(Request $request, Team $current_team): Response
    {
        Gate::authorize('manageSettings', $current_team);

        $settings = $current_team->ensureOrganizationSetting();

        return Inertia::render('organizations/preferences', [
            'team' => [
                'id' => $current_team->id,
                'name' => $current_team->name,
                'slug' => $current_team->slug,
            ],
            'modules' => collect(OrganizationSetting::optionalModules())
                ->map(fn (string $label, string $key) => [
                    'key' => $key,
                    'label' => $label,
                    'enabled' => $settings->moduleEnabled($key),
                    'locked' => $settings->moduleLocked($key),
                ])->values(),
            'notifications' => collect(OrganizationSetting::notificationOptions())
                ->map(fn (string $label, string $key) => [
                    'key' => $key,
                    'label' => $label,
                    'enabled' => $settings->notificationEnabled($key),
                ])->values(),
            'workflows' => WorkflowDefinition::query()
                ->where('team_id', $current_team->id)
                ->orderBy('id')
                ->get()
                ->map(fn (WorkflowDefinition $workflow) => [
                    'id' => $workflow->uuid,
                    'name' => $workflow->name,
                    'description' => $workflow->description,
                    'trigger' => $workflow->trigger,
                    'is_enabled' => $workflow->is_enabled,
                ])->values(),
        ]);
    }

    public function update(UpdateOrganizationPreferencesRequest $request, Team $current_team): RedirectResponse
    {
        $settings = $current_team->ensureOrganizationSetting();

        $settings->update([
            'disabled_modules' => collect($request->validated('modules'))
                ->reject(fn ($enabled) => (bool) $enabled)
                ->keys()
                ->reject(fn (string $key) => $settings->moduleLocked($key))
                ->merge(collect($settings->disabled_modules ?? [])->filter(fn (string $key) => $settings->moduleLocked($key)))
                ->unique()
                ->values()
                ->all(),
            'notification_settings' => collect($request->validated('notifications'))
                ->map(fn ($enabled) => (bool) $enabled)
                ->all(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Preferences saved.')]);

        return back();
    }

    public function toggleWorkflow(Request $request, Team $current_team, WorkflowDefinition $workflow): RedirectResponse
    {
        Gate::authorize('manageSettings', $current_team);
        abort_unless($workflow->team_id === $current_team->id, 404);

        $workflow->update(['is_enabled' => $request->boolean('is_enabled')]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $workflow->is_enabled ? __('Workflow enabled.') : __('Workflow disabled.'),
        ]);

        return back();
    }
}
