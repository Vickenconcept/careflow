<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\UpdateOrganizationAiRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Turns the hospital's AI features on or off straight from their switches.
 */
class OrganizationAiController extends Controller
{
    public function update(UpdateOrganizationAiRequest $request, Team $current_team): RedirectResponse
    {
        $changes = $request->safe()->only(['ai_processing_enabled', 'assistant_enabled']);

        $current_team->ensureOrganizationSetting()->update(
            array_map(fn ($value) => (bool) $value, $changes),
        );

        $messages = [];

        if (array_key_exists('ai_processing_enabled', $changes)) {
            $messages[] = $changes['ai_processing_enabled'] ? 'AI document processing is on.' : 'AI document processing is off.';
        }

        if (array_key_exists('assistant_enabled', $changes)) {
            $messages[] = $changes['assistant_enabled'] ? 'The AI assistant is on.' : 'The AI assistant is off.';
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => implode(' ', $messages)]);

        return back();
    }
}
