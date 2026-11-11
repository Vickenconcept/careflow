<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\DecideAssistantActionRequest;
use App\Http\Requests\Organizations\SendAssistantMessageRequest;
use App\Http\Requests\Organizations\UpdateAssistantSettingsRequest;
use App\Models\Team;
use App\Services\Assistant\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AssistantController extends Controller
{
    public function __construct(private AssistantService $assistant) {}

    /**
     * The command center page.
     */
    public function show(Request $request, Team $current_team): Response
    {
        $user = $request->user();
        $settings = $current_team->ensureOrganizationSetting();
        $canManage = $user->can('manageSettings', $current_team);
        $enabled = (bool) $settings->assistant_enabled;

        return Inertia::render('organizations/assistant', [
            'team' => $current_team->only(['id', 'name', 'slug']),
            'assistant' => [
                'enabled' => $enabled,
                'name' => $settings->assistant_name ?: 'Nora',
                'knowledge' => $canManage ? (string) $settings->assistant_knowledge : null,
                'canManage' => $canManage,
                'capabilities' => $this->assistant->capabilities($user, $current_team),
            ],
            'chat' => $enabled ? $this->assistant->state($user, $current_team) : ['messages' => [], 'pending' => []],
        ]);
    }

    /**
     * Current chat state, for the floating widget.
     */
    public function state(Request $request, Team $current_team): JsonResponse
    {
        if (! $this->assistant->isEnabled($current_team)) {
            return $this->disabled();
        }

        return response()->json($this->assistant->state($request->user(), $current_team));
    }

    public function message(SendAssistantMessageRequest $request, Team $current_team): JsonResponse
    {
        if (! $this->assistant->isEnabled($current_team)) {
            return $this->disabled();
        }

        return $this->run(fn () => $this->assistant->send(
            $request->user(),
            $current_team,
            trim((string) $request->validated('message')),
        ));
    }

    public function decide(DecideAssistantActionRequest $request, Team $current_team): JsonResponse
    {
        if (! $this->assistant->isEnabled($current_team)) {
            return $this->disabled();
        }

        return $this->run(fn () => $this->assistant->decide(
            $request->user(),
            $current_team,
            (string) $request->validated('tool_call_id'),
            (bool) $request->validated('approve'),
        ));
    }

    public function clear(Request $request, Team $current_team): JsonResponse
    {
        $this->assistant->clear($request->user(), $current_team);

        return response()->json(['messages' => [], 'pending' => []]);
    }

    public function updateSettings(UpdateAssistantSettingsRequest $request, Team $current_team): RedirectResponse
    {
        $data = [
            'assistant_name' => trim((string) $request->validated('assistant_name')),
            'assistant_knowledge' => $request->validated('assistant_knowledge'),
        ];

        if ($request->has('assistant_enabled')) {
            $data['assistant_enabled'] = $request->boolean('assistant_enabled');
        }

        $current_team->ensureOrganizationSetting()->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Assistant settings saved.']);

        return back();
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     */
    private function run(callable $callback): JsonResponse
    {
        try {
            return response()->json($callback());
        } catch (Throwable $exception) {
            Log::error('Assistant request failed', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'The assistant could not answer just now. Please try again in a moment.',
            ], 502);
        }
    }

    private function disabled(): JsonResponse
    {
        return response()->json([
            'message' => 'The assistant is switched off for this hospital.',
        ], 403);
    }
}
