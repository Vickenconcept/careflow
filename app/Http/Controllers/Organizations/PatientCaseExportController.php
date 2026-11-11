<?php

namespace App\Http\Controllers\Organizations;

use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\FilterPatientCasesRequest;
use App\Models\PatientCase;
use App\Models\Team;
use App\Queries\PatientCaseIndexQuery;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatientCaseExportController extends Controller
{
    private const HEADINGS = [
        'Case reference',
        'Patient',
        'Country',
        'Preferred language',
        'Department',
        'Enquiry date',
        'Coordinator',
        'Doctor',
        'Status',
        'Priority',
        'Last activity',
        'Open tasks',
    ];

    public function __invoke(
        FilterPatientCasesRequest $request,
        Team $current_team,
        PatientCaseIndexQuery $query,
    ): StreamedResponse {
        Gate::authorize('viewAny', [PatientCase::class, $current_team]);
        abort_unless($request->user()->hasTeamPermission($current_team, TeamPermission::ManageCases), 403);

        $builder = $query->exportQuery($request->user(), $current_team, $request->filters());
        $filename = sprintf('cases-%s.csv', now()->format('Y-m-d-His'));

        return response()->streamDownload(function () use ($builder): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, self::HEADINGS);

            $builder->chunk(200, function ($cases) use ($out): void {
                foreach ($cases as $case) {
                    fputcsv($out, array_map($this->safeCell(...), [
                        $case->reference,
                        $case->patient?->fullName(),
                        $case->patient?->country_of_residence,
                        $case->patient?->preferred_language,
                        $case->department?->name,
                        $case->enquiry?->submitted_at?->toDateString(),
                        $case->assignedCoordinator?->name,
                        $case->assignedDoctor?->name,
                        $case->status->label(),
                        $case->priority->label(),
                        $case->last_activity_at?->toDateTimeString(),
                        (string) $case->open_tasks_count,
                    ]));
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Neutralise spreadsheet formula injection from patient-entered values.
     */
    private function safeCell(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'".$text
            : $text;
    }
}
