<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\ReorderDepartment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\ReorderDepartmentRequest;
use App\Http\Requests\Organizations\StoreDepartmentRequest;
use App\Http\Requests\Organizations\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    /**
     * Display a listing of departments for the organization.
     */
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [Department::class, $current_team]);

        return Inertia::render('organizations/departments/index', [
            'team' => [
                'id' => $current_team->id,
                'name' => $current_team->name,
                'slug' => $current_team->slug,
            ],
            'departments' => $current_team->departments()
                ->get()
                ->map(fn (Department $department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'slug' => $department->slug,
                    'code' => $department->code,
                    'description' => $department->description,
                    'is_active' => $department->is_active,
                    'sort_order' => $department->sort_order,
                ]),
            'permissions' => [
                'canManageDepartments' => $request->user()->can('create', [Department::class, $current_team]),
            ],
        ]);
    }

    /**
     * Departments are added in a dialog on the list page.
     */
    public function create(Request $request, Team $current_team): RedirectResponse
    {
        Gate::authorize('create', [Department::class, $current_team]);

        return to_route('organizations.departments.index', ['current_team' => $current_team->slug]);
    }

    /**
     * Store a newly created department.
     */
    public function store(StoreDepartmentRequest $request, Team $current_team): RedirectResponse
    {
        $validated = $request->safe()->except(['sort_order']);

        $current_team->departments()->create([
            ...$validated,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => ((int) $current_team->departments()->max('sort_order')) + 1,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Department created.'),
        ]);

        return to_route('organizations.departments.index', [
            'current_team' => $current_team->slug,
        ]);
    }

    /**
     * Departments are edited in a dialog on the list page.
     */
    public function edit(Request $request, Team $current_team, Department $department): RedirectResponse
    {
        $this->ensureDepartmentBelongsToTeam($department, $current_team);
        Gate::authorize('update', $department);

        return to_route('organizations.departments.index', ['current_team' => $current_team->slug]);
    }

    /**
     * Update the specified department.
     */
    public function update(
        UpdateDepartmentRequest $request,
        Team $current_team,
        Department $department,
    ): RedirectResponse {
        $this->ensureDepartmentBelongsToTeam($department, $current_team);

        $department->update($request->safe()->except(['sort_order']));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Department updated.'),
        ]);

        return to_route('organizations.departments.index', [
            'current_team' => $current_team->slug,
        ]);
    }

    /**
     * Move a department one position up or down in the intake list.
     */
    public function reorder(
        ReorderDepartmentRequest $request,
        Team $current_team,
        Department $department,
        ReorderDepartment $reorderDepartment,
    ): RedirectResponse {
        $this->ensureDepartmentBelongsToTeam($department, $current_team);

        $reorderDepartment->handle(
            team: $current_team,
            department: $department,
            direction: $request->validated('direction'),
        );

        return back();
    }

    /**
     * Remove the specified department.
     */
    public function destroy(Request $request, Team $current_team, Department $department): RedirectResponse
    {
        $this->ensureDepartmentBelongsToTeam($department, $current_team);
        Gate::authorize('delete', $department);

        $department->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Department deleted.'),
        ]);

        return to_route('organizations.departments.index', [
            'current_team' => $current_team->slug,
        ]);
    }

    /**
     * Prevent cross-tenant department access.
     */
    protected function ensureDepartmentBelongsToTeam(Department $department, Team $team): void
    {
        abort_unless($department->team_id === $team->id, 404);
    }
}
