<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\UpdateOrganizationSettingsRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationSettingsController extends Controller
{
    /**
     * Show the organization settings form.
     */
    public function edit(Request $request, Team $current_team): Response
    {
        Gate::authorize('view', $current_team);

        $settings = $current_team->ensureOrganizationSetting();

        return Inertia::render('organizations/settings', [
            'team' => [
                'id' => $current_team->id,
                'name' => $current_team->name,
                'slug' => $current_team->slug,
            ],
            'logoUrl' => $settings->logo_path ? route('portal.logo', ['organization' => $current_team->slug]).'?v='.$settings->updated_at?->timestamp : null,
            'settings' => [
                'legal_name' => $settings->legal_name,
                'contact_email' => $settings->contact_email,
                'contact_phone' => $settings->contact_phone,
                'address_line_1' => $settings->address_line_1,
                'address_line_2' => $settings->address_line_2,
                'city' => $settings->city,
                'state' => $settings->state,
                'postal_code' => $settings->postal_code,
                'country' => $settings->country,
                'timezone' => $settings->timezone,
                'default_currency' => $settings->default_currency,
                'default_language' => $settings->default_language,
                'supported_languages' => $settings->supported_languages ?? ['en'],
                'brand_primary_color' => $settings->brand_primary_color,
                'brand_secondary_color' => $settings->brand_secondary_color,
                'ai_processing_enabled' => $settings->ai_processing_enabled,
                'ai_provider' => $settings->ai_provider,
                'assistant_enabled' => (bool) $settings->assistant_enabled,
                'assistant_name' => $settings->assistant_name ?: 'Nora',
            ],
            'permissions' => [
                'canManageOrganizationSettings' => $request->user()->can('manageSettings', $current_team),
            ],
            'languageOptions' => [
                ['value' => 'en', 'label' => 'English'],
                ['value' => 'tr', 'label' => 'Turkish'],
                ['value' => 'ar', 'label' => 'Arabic'],
                ['value' => 'ru', 'label' => 'Russian'],
                ['value' => 'az', 'label' => 'Azerbaijani'],
            ],
        ]);
    }

    /**
     * Update organization settings.
     */
    public function update(UpdateOrganizationSettingsRequest $request, Team $current_team): RedirectResponse
    {
        $settings = $current_team->ensureOrganizationSetting();

        $settings->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Organization settings updated.'),
        ]);

        return to_route('organizations.settings.edit', [
            'current_team' => $current_team->slug,
        ]);
    }
}
