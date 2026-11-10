import { Head, Link } from '@inertiajs/react';
import { ClipboardList, Eye } from 'lucide-react';
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
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type QueueItem = {
    reference: string;
    status: string;
    status_label: string;
    priority: string;
    patient_name: string;
    department: string | null;
    assigned_coordinator: string | null;
    assigned_doctor: string | null;
    assigned_to_me: boolean;
    last_activity_at: string | null;
    is_demo: boolean;
};

type Props = {
    team: { id: number; name: string; slug: string };
    queue: QueueItem[];
};

const STATUS_STYLES: Record<string, string> = {
    awaiting_doctor_review: 'border-indigo-200 bg-indigo-50 text-indigo-700',
    additional_information_required:
        'border-amber-200 bg-amber-50 text-amber-700',
    treatment_proposal_preparation: 'border-cyan-200 bg-cyan-50 text-cyan-700',
};

function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

export default function DoctorQueue({ team, queue }: Props) {
    return (
        <>
            <Head title="Doctor queue" />
            <div className="space-y-5 p-4 md:p-6">
                <Heading
                    title="Doctor queue"
                    description="Clinical cases awaiting doctor review, additional information, or proposal preparation."
                />

                {queue.length === 0 ? (
                    <EmptyState
                        icon={ClipboardList}
                        title="Queue is clear"
                        description="No clinical backlog items are visible to you right now. New cases needing review will show up here."
                    />
                ) : (
                    <DataTableCard>
                        <DataTable minWidthClassName="min-w-[900px]">
                            <DataTableHeader>
                                <DataTableHead>Patient</DataTableHead>
                                <DataTableHead>Status</DataTableHead>
                                <DataTableHead>Department</DataTableHead>
                                <DataTableHead>Coordinator</DataTableHead>
                                <DataTableHead>Doctor</DataTableHead>
                                <DataTableHead className="text-right">
                                    Actions
                                </DataTableHead>
                            </DataTableHeader>
                            <DataTableBody>
                                {queue.map((item) => {
                                    const caseHref = `/${team.slug}/cases/${item.reference}`;

                                    return (
                                        <DataTableRow key={item.reference}>
                                            <DataTableCell>
                                                <div className="flex items-start gap-3">
                                                    <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full bg-brand/10 text-xs font-semibold text-brand">
                                                        {initials(
                                                            item.patient_name,
                                                        )}
                                                    </span>
                                                    <div className="min-w-0">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <Link
                                                                href={caseHref}
                                                                className="truncate font-semibold text-slate-900 hover:text-brand"
                                                            >
                                                                {
                                                                    item.patient_name
                                                                }
                                                            </Link>
                                                            {item.assigned_to_me ? (
                                                                <Badge
                                                                    variant="secondary"
                                                                    className="rounded-full"
                                                                >
                                                                    Assigned to
                                                                    me
                                                                </Badge>
                                                            ) : null}
                                                            {item.is_demo ? (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="rounded-full"
                                                                >
                                                                    Demo
                                                                </Badge>
                                                            ) : null}
                                                        </div>
                                                        <div className="mt-1 font-mono text-[11px] text-brand">
                                                            {item.reference}
                                                        </div>
                                                    </div>
                                                </div>
                                            </DataTableCell>
                                            <DataTableCell>
                                                <span
                                                    className={cn(
                                                        'inline-flex max-w-[200px] truncate rounded-full border px-2.5 py-1 text-xs font-medium',
                                                        STATUS_STYLES[
                                                            item.status
                                                        ] ??
                                                            'border-slate-200 bg-slate-50 text-slate-600',
                                                    )}
                                                >
                                                    {item.status_label}
                                                </span>
                                            </DataTableCell>
                                            <DataTableCell className="text-slate-700">
                                                {item.department ?? (
                                                    <span className="text-slate-400">
                                                        —
                                                    </span>
                                                )}
                                            </DataTableCell>
                                            <DataTableCell className="text-slate-500">
                                                {item.assigned_coordinator ??
                                                    '—'}
                                            </DataTableCell>
                                            <DataTableCell className="text-slate-500">
                                                {item.assigned_doctor ??
                                                    'Unassigned'}
                                            </DataTableCell>
                                            <DataTableCell className="text-right">
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                    className="rounded-full"
                                                >
                                                    <Link href={caseHref}>
                                                        <Eye className="size-3.5" />
                                                        Open
                                                    </Link>
                                                </Button>
                                            </DataTableCell>
                                        </DataTableRow>
                                    );
                                })}
                            </DataTableBody>
                        </DataTable>
                    </DataTableCard>
                )}
            </div>
        </>
    );
}

DoctorQueue.layout = (props: { team: { slug: string } }) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard(props.team.slug) },
        {
            title: 'Doctor queue',
            href: `/${props.team.slug}/doctor-queue`,
        },
    ],
});
