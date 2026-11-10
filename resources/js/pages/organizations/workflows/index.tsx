import { Head, Link } from '@inertiajs/react';
import { Workflow } from 'lucide-react';
import {
    DataTable,
    DataTableBody,
    DataTableCard,
    DataTableCell,
    DataTableHead,
    DataTableHeader,
    DataTableRow,
} from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type Execution = {
    id: number;
    uuid: string;
    status: string;
    status_label: string;
    idempotency_key: string;
    started_at: string | null;
    finished_at: string | null;
    workflow: string;
    case_reference: string | null;
    error_message: string | null;
};

type Props = {
    team: { id: number; name: string; slug: string };
    executions: Execution[];
};

function statusTone(status: string): string {
    if (status === 'completed') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }

    if (status === 'failed') {
        return 'border-rose-200 bg-rose-50 text-rose-700';
    }

    if (status === 'running') {
        return 'border-sky-200 bg-sky-50 text-sky-700';
    }

    return 'border-slate-200 bg-slate-50 text-slate-600';
}

export default function WorkflowsIndex({ team, executions }: Props) {
    return (
        <>
            <Head title="Workflow executions" />
            <div className="space-y-5 p-4 md:p-6">
                <Heading
                    title="Workflow executions"
                    description={`Recent automation runs for ${team.name}. External patient messages still require staff approval.`}
                />

                {executions.length === 0 ? (
                    <EmptyState
                        icon={Workflow}
                        title="No workflow runs yet"
                        description="Automation executions will appear here after intake, document processing, and other workflows start."
                    />
                ) : (
                    <DataTableCard>
                        <DataTable>
                            <DataTableHeader>
                                <DataTableHead>Workflow</DataTableHead>
                                <DataTableHead>Case</DataTableHead>
                                <DataTableHead>Status</DataTableHead>
                                <DataTableHead>Started</DataTableHead>
                                <DataTableHead>Finished</DataTableHead>
                            </DataTableHeader>
                            <DataTableBody>
                                {executions.map((execution) => (
                                    <DataTableRow key={execution.uuid}>
                                        <DataTableCell>
                                            <div className="font-semibold text-slate-900">
                                                {execution.workflow}
                                            </div>
                                            <div className="mt-1 font-mono text-[11px] text-slate-500">
                                                {execution.idempotency_key}
                                            </div>
                                            {execution.error_message ? (
                                                <p className="mt-2 max-w-md text-xs text-rose-600">
                                                    {execution.error_message}
                                                </p>
                                            ) : null}
                                        </DataTableCell>
                                        <DataTableCell>
                                            {execution.case_reference ? (
                                                <Link
                                                    href={`/${team.slug}/cases/${execution.case_reference}`}
                                                    className="font-mono text-xs font-medium text-brand hover:underline"
                                                >
                                                    {execution.case_reference}
                                                </Link>
                                            ) : (
                                                <span className="text-slate-400">
                                                    —
                                                </span>
                                            )}
                                        </DataTableCell>
                                        <DataTableCell>
                                            <Badge
                                                className={cn(
                                                    'rounded-full border px-2.5 py-1 text-xs font-semibold',
                                                    statusTone(
                                                        execution.status,
                                                    ),
                                                )}
                                            >
                                                {execution.status_label}
                                            </Badge>
                                        </DataTableCell>
                                        <DataTableCell className="whitespace-nowrap text-slate-500">
                                            {execution.started_at
                                                ? new Date(
                                                      execution.started_at,
                                                  ).toLocaleString()
                                                : '—'}
                                        </DataTableCell>
                                        <DataTableCell className="whitespace-nowrap text-slate-500">
                                            {execution.finished_at
                                                ? new Date(
                                                      execution.finished_at,
                                                  ).toLocaleString()
                                                : '—'}
                                        </DataTableCell>
                                    </DataTableRow>
                                ))}
                            </DataTableBody>
                        </DataTable>
                    </DataTableCard>
                )}
            </div>
        </>
    );
}

WorkflowsIndex.layout = (props: { team: { slug: string } }) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard(props.team.slug) },
        {
            title: 'Workflows',
            href: `/${props.team.slug}/workflows`,
        },
    ],
});
