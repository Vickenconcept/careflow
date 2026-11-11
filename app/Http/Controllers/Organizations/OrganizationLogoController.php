<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationLogoController extends Controller
{
    /**
     * Public logo for patient-facing pages. Raster images only; never SVG.
     */
    public function show(Team $organization): StreamedResponse
    {
        $path = $organization->organizationSetting?->logo_path;

        abort_if($path === null || ! Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, [
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(Request $request, Team $current_team): RedirectResponse
    {
        Gate::authorize('manageSettings', $current_team);

        $request->validate([
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ]);

        $settings = $current_team->ensureOrganizationSetting();
        $previous = $settings->logo_path;

        $settings->update([
            'logo_path' => $request->file('logo')->store('logos/'.$current_team->id, 'public'),
        ]);

        if ($previous !== null) {
            Storage::disk('public')->delete($previous);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logo updated.')]);

        return back();
    }

    public function destroy(Team $current_team): RedirectResponse
    {
        Gate::authorize('manageSettings', $current_team);

        $settings = $current_team->ensureOrganizationSetting();

        if ($settings->logo_path !== null) {
            Storage::disk('public')->delete($settings->logo_path);
            $settings->update(['logo_path' => null]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logo removed.')]);

        return back();
    }
}
