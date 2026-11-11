import { Head, Link } from '@inertiajs/react';
import { ClipboardList, Eye, FileText, Stethoscope } from 'lucide-react';
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
import { RowAction, RowActions } from '@/components/row-actions';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
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
    stats?: {
        awaiting_review: number;
        average_review_hours: number | null;
        reviews_last_90_days: number;
    };
    recentlyReviewed?: Array<{
        reference: string;
        patient_name: string | null;
        outcome: string | null;
        completed_at: string | null;
        url: string;
    }>;
    upcoming?: Array<{
        reference: string;
        patient_name: string | null;
        type: string;
        starts_at: string;
        location: string | null;
        url: string;
    }>;
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

export default function DoctorQueue({
    team,
    queue,
    stats,
    recentlyReviewed = [],
    upcoming = [],
}: Props) {
    return (
        <>
            <Head title="Doctor queue" />
            <div className="space-y-5 p-4 md:p-6">
                <Heading
                    title="Doctor queue"
                    description="Clinical cases awaiting doctor review, additional information, or proposal preparation."
                />

                {stats ? (
                    <div className="grid gap-3 sm:grid-cols-3">
                        <div className="rounded-xl border border-slate-200 bg-white p-4">
                            <p className="text-xs font-medium text-slate-500">
                                Awaiting your review
                            </p>
                            <p className="mt-1 text-2xl font-semibold">
                                {stats.awaiting_review}
                            </p>
                        </div>
                        <div className="rounded-xl border border-slate-200 bg-white p-4">
                            <p className="text-xs font-medium text-slate-500">
                                Average review time
                            </p>
                            <p className="mt-1 text-2xl font-semibold">
                                {stats.average_review_hours === null
                                    ? '—'
                                    : `${stats.average_review_hours} h`}
                            </p>
                            <p className="text-xs text-slate-500">
                                From starting a review to completing it, last 90
                                days
                            </p>
                        </div>
                        <div className="rounded-xl border border-slate-200 bg-white p-4">
                            <p className="text-xs font-medium text-slate-500">
                                Reviews completed
                            </p>
                            <p className="mt-1 text-2xl font-semibold">
                                {stats.reviews_last_90_days}
                            </p>
                            <p className="text-xs text-slate-500">
                                Last 90 days
                            </p>
                        </div>
                    </div>
                ) : null}

                {upcoming.length > 0 || recentlyReviewed.length > 0 ? (
                    <div className="grid gap-4 lg:grid-cols-2">
                        <section className="rounded-xl border border-slate-200 bg-white">
                            <h2 className="border-b border-slate-100 px-4 py-3 text-sm font-semibold">
                                Upcoming consultations
                            </h2>
                            {upcoming.length === 0 ? (
                                <p className="px-4 py-4 text-sm text-slate-500">
                                    No confirmed consultations booked with you.
                                </p>
                            ) : (
                                <ul className="divide-y divide-slate-100">
                                    {upcoming.map((item) => (
                                        <li key={`${item.reference}-${item.starts_at}`}>
                                            <Link
                                                href={item.url}
                                                className="block px-4 py-3 text-sm hover:bg-slate-50"
                                            >
                                                <p className="font-medium">
                                                    {item.patient_name ??
                                                        item.reference}{' '}
                                                    · {item.type}
                                                </p>
                                                <p className="text-xs text-slate-500">
                                                    {new Date(
                                                        item.starts_at,
                                                    ).toLocaleString()}
                                                    {item.location
                                                        ? ` · ${item.location}`
                                                        : ''}
                                                </p>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                        <section className="rounded-xl border border-slate-200 bg-white">
                            <h2 className="border-b border-slate-100 px-4 py-3 text-sm font-semibold">
                                Recently reviewed
                            </h2>
                            {recentlyReviewed.length === 0 ? (
                                <p className="px-4 py-4 text-sm text-slate-500">
                                    You have not completed a review yet.
                                </p>
                            ) : (
                                <ul className="divide-y divide-slate-100">
                                    {recentlyReviewed.map((item) => (
                                        <li key={`${item.reference}-${item.completed_at}`}>
                                            <Link
                                                href={item.url}
                                                className="block px-4 py-3 text-sm hover:bg-slate-50"
                                            >
                                                <p className="font-medium">
                                                    {item.patient_name ??
                                                        item.reference}
                                                    {item.outcome
                                                        ? ` · ${item.outcome}`
                                                        : ''}
                                                </p>
                                                <p className="text-xs text-slate-500">
                                                    {item.completed_at
                                                        ? new Date(
                                                              item.completed_at,
                                                          ).toLocaleString()
                                                        : ''}
                                                </p>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </div>
                ) : null}

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
                                    const caseHref = `/${team.slug}/cases/${item.reference}?tab=evaluation`;

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
                                            <DataTableCell>
                                                <RowActions>
                                                    <RowAction
                                                        label="Review case"
                                                        icon={Stethoscope}
                                                        href={caseHref}
                                                        tone="primary"
                                                        showLabel
                                                    />
                                                    <RowAction
                                                        label="Open documents"
                                                        icon={FileText}
                                                        href={`/${team.slug}/cases/${item.reference}?tab=documents`}
                                                    />
                                                    <RowAction
                                                        label="Case overview"
                                                        icon={Eye}
                                                        href={`/${team.slug}/cases/${item.reference}`}
                                                    />
                                                </RowActions>
                                            </DataTableCell>                                        </DataTableRow>
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
