import { Head, Link, router } from '@inertiajs/react';
import { CalendarCheck, ClipboardList, Eye, Plane, ScrollText } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import {
    PaginationLinks,
    type PaginationLink,
} from '@/components/pagination-links';
import PageTabs from '@/components/page-tabs';
import { RowAction, RowActions } from '@/components/row-actions';

const TAB_ICONS: Record<string, LucideIcon> = {
    proposals: ScrollText,
    appointments: CalendarCheck,
    travel: Plane,
};

type Row = {
    id: string;
    primary: string | null;
    secondary: string | null;
    status_label: string;
    detail: string | null;
    case: { reference: string; patient_name: string | null; url: string } | null;
};

type Props = {
    team: { id: number; name: string; slug: string };
    tabs: Array<{ id: string; label: string }>;
    active: string;
    status: string;
    statusOptions: Array<{ value: string; label: string }>;
    rows: { data: Row[]; links: PaginationLink[]; total?: number };
};

export default function Coordination({
    team,
    tabs,
    active,
    status,
    statusOptions,
    rows,
}: Props) {
    const base = `/${team.slug}/coordination`;

    return (
        <>
            <Head title="Coordination" />
            <div className="space-y-5 p-4 md:p-6">
                <header className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Coordination
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Treatment proposals, appointments and travel across your
                        cases.
                    </p>
                </header>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <PageTabs
                        label="Coordination"
                        active={active}
                        tabs={tabs.map((tab) => ({
                            ...tab,
                            icon: TAB_ICONS[tab.id],
                            href: `${base}?tab=${tab.id}`,
                        }))}
                    />
                    <label className="flex items-center gap-2 text-sm">
                        <span className="text-muted-foreground">Status</span>
                        <select
                            value={status}
                            onChange={(event) =>
                                router.get(
                                    base,
                                    {
                                        tab: active,
                                        status: event.target.value || undefined,
                                    },
                                    { preserveState: true, replace: true },
                                )
                            }
                            className="h-9 rounded-lg border border-border bg-card px-2 text-sm"
                        >
                            <option value="">All</option>
                            {statusOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>

                {rows.data.length === 0 ? (
                    <EmptyState
                        icon={ClipboardList}
                        title="Nothing to show"
                        description={
                            status
                                ? 'No items have this status. Try another status.'
                                : 'Items appear here as your team creates them on cases.'
                        }
                    />
                ) : (
                    <div className="overflow-hidden rounded-lg border border-border bg-card">
                        <ul className="divide-y divide-border">
                            {rows.data.map((row) => (
                                <li
                                    key={row.id}
                                    className="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm hover:bg-muted/40"
                                >
                                    <div className="flex min-w-0 flex-1 flex-wrap items-center justify-between gap-3">
                                        <RowBody row={row} />
                                    </div>
                                    {row.case ? (
                                        <RowActions>
                                            <RowAction
                                                label="Open"
                                                icon={TAB_ICONS[active] ?? ClipboardList}
                                                href={row.case.url}
                                                tone="primary"
                                                showLabel
                                            />
                                            <RowAction
                                                label="Case overview"
                                                icon={Eye}
                                                href={row.case.url.split('?')[0]}
                                            />
                                        </RowActions>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                        <PaginationLinks links={rows.links} />
                    </div>
                )}
            </div>
        </>
    );
}

function RowBody({ row }: { row: Row }) {
    return (
        <>
            <div className="min-w-0">
                <p className="truncate font-medium">{row.primary}</p>
                <p className="truncate text-xs text-muted-foreground">
                    {row.case ? (
                        <>
                            <span className="font-mono text-brand">
                                {row.case.reference}
                            </span>
                            {row.case.patient_name
                                ? ` · ${row.case.patient_name}`
                                : ''}
                        </>
                    ) : null}
                    {row.secondary ? ` · ${row.secondary}` : ''}
                </p>
            </div>
            <div className="text-right">
                <span className="rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium">
                    {row.status_label}
                </span>
                {row.detail ? (
                    <p className="mt-1 text-xs text-muted-foreground">
                        {row.detail}
                    </p>
                ) : null}
            </div>
        </>
    );
}
