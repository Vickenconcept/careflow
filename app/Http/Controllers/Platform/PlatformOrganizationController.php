<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\OrganizationSetting;
use App\Models\PatientCase;
use App\Models\PatientDocument;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlatformOrganizationController extends Controller
{
    /**
     * Plan, entitlements and usage for one organization.
     */
    public function edit(Team $organization): Response
    {
        $settings = $organization->ensureOrganizationSetting();

        return Inertia::render('platform/organization', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'subscription' => [
                'plan' => $settings->plan,
                'subscription_status' => $settings->subscription_status,
                'seat_limit' => $settings->seat_limit,
                'locked_modules' => $settings->locked_modules ?? [],
                'platform_notes' => $settings->platform_notes,
            ],
            'planOptions' => $this->options(OrganizationSetting::plans()),
            'statusOptions' => $this->options(OrganizationSetting::subscriptionStatuses()),
            'moduleOptions' => $this->options(OrganizationSetting::optionalModules()),
            'usage' => self::usageFor($organization),
        ]);
    }

    public function update(Request $request, Team $organization): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', Rule::in(array_keys(OrganizationSetting::plans()))],
            'subscription_status' => ['required', Rule::in(array_keys(OrganizationSetting::subscriptionStatuses()))],
            'seat_limit' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'locked_modules' => ['nullable', 'array'],
            'locked_modules.*' => [Rule::in(array_keys(OrganizationSetting::optionalModules()))],
            'platform_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $organization->ensureOrganizationSetting()->update([
            'plan' => $validated['plan'],
            'subscription_status' => $validated['subscription_status'],
            'seat_limit' => $validated['seat_limit'] ?? null,
            'locked_modules' => array_values($validated['locked_modules'] ?? []),
            'platform_notes' => $validated['platform_notes'] ?? null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscription saved.')]);

        return back();
    }

    /**
     * @return array{staff: int, pending_invitations: int, cases_total: int, cases_30_days: int, documents: int, storage_mb: float}
     */
    public static function usageFor(Team $team): array
    {
        return [
            'staff' => $team->members()->count(),
            'pending_invitations' => $team->invitations()->whereNull('accepted_at')->where('expires_at', '>', now())->count(),
            'cases_total' => PatientCase::query()->where('team_id', $team->id)->count(),
            'cases_30_days' => PatientCase::query()->where('team_id', $team->id)->where('created_at', '>=', now()->subDays(30))->count(),
            'documents' => PatientDocument::query()->where('team_id', $team->id)->count(),
            'storage_mb' => round(((int) PatientDocument::query()->where('team_id', $team->id)->sum('size_bytes')) / 1048576, 1),
        ];
    }

    /**
     * @param  array<string, string>  $items
     * @return list<array{value: string, label: string}>
     */
    protected function options(array $items): array
    {
        return collect($items)->map(fn (string $label, string $value) => compact('value', 'label'))->values()->all();
    }
}
