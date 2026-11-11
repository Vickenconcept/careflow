<?php

namespace App\Http\Controllers\Organizations;

use App\Enums\CasePriority;
use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Models\PatientCase;
use App\Models\SavedCaseView;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SavedCaseViewController extends Controller
{
    public function store(Request $request, Team $current_team): RedirectResponse
    {
        Gate::authorize('viewAny', [PatientCase::class, $current_team]);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:60',
                Rule::unique('saved_case_views', 'name')
                    ->where('team_id', $current_team->id)
                    ->where('user_id', $request->user()->id),
            ],
            'filters' => ['nullable', 'array'],
            'filters.search' => ['nullable', 'string', 'max:120'],
            'filters.status' => ['nullable', 'string', Rule::enum(CaseStatus::class)],
            'filters.priority' => ['nullable', 'string', Rule::enum(CasePriority::class)],
            'filters.department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where('team_id', $current_team->id),
            ],
            'filters.assignment' => ['nullable', 'string', Rule::in(['mine', 'unassigned', 'unassigned_doctor'])],
            'filters.sort' => ['nullable', 'string', Rule::in(['recent', 'oldest', 'reference', 'status'])],
        ]);

        $filters = array_filter($validated['filters'] ?? [], fn ($value) => $value !== null && $value !== '');

        abort_if($filters === [], 422, 'Choose at least one filter before saving a view.');

        SavedCaseView::query()->create([
            'team_id' => $current_team->id,
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'filters' => $filters,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('View saved.')]);

        return back();
    }

    public function destroy(Request $request, Team $current_team, SavedCaseView $view): RedirectResponse
    {
        abort_unless($view->team_id === $current_team->id && $view->user_id === $request->user()->id, 404);

        $view->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('View removed.')]);

        return back();
    }
}
