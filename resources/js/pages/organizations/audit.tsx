import { Head } from '@inertiajs/react';
import { Activity, Eye, ScrollText } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PaginationLinks } from '@/components/pagination-links';
import OrganizationTabs from '@/components/organization-tabs';
import PageTabs from '@/components/page-tabs';

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    total?: number;
};

type AccessRow = {
    id: number;
    action: string;
    user: string;
    document: string | null;
    case_reference: string | null;
    ip_address: string | null;
    accessed_at: string;
};

type ActivityRow = {
    id: number;
    type: string;
    summary: string;
    actor: string;
    case_reference: string | null;
    patient_visible: boolean;
    occurred_at: string;
};

type Props = {
    team: { id: number; name: string; slug: string };
    view: 'documents' | 'activity';
    documentAccess: Paginated<AccessRow> | null;
    activity: Paginated<ActivityRow> | null;
};

const ACTION_LABELS: Record<string, string> = {
    view: 'Opened',
    preview: 'Previewed',
    download: 'Downloaded',
};

function formatTime(value: string): string {
    return new Date(value).toLocaleString();
}

export default function Audit({ team, view, documentAccess, activity }: Props) {
    const base = `/${team.slug}/organization/audit`;

    return (
        <>
            <Head title="Audit log" />
            <OrganizationTabs />
            <div className="space-y-5 p-4 md:p-6">
                <header className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Audit log
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Who opened patient documents and what happened on each
                        case. Entries cannot be edited here.
                    </p>
                </header>

                <PageTabs
                    label="Audit views"
                    active={view}
                    tabs={[
                        { id: 'documents', label: 'Document access', icon: Eye, href: `${base}?view=documents` },
                        { id: 'activity', label: 'Case activity', icon: Activity, href: `${base}?view=activity` },
                    ]}
                />

                {view === 'documents' && documentAccess ? (
                    documentAccess.data.length === 0 ? (
                        <EmptyState
                            icon={ScrollText}
                            title="No document access recorded yet"
                            description="Each time staff open, preview or download a patient document it will be listed here."
                        />
                    ) : (
                        <div className="overflow-hidden rounded-lg border border-border bg-card">
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[640px] text-sm">
                                    <thead>
                                        <tr className="border-b border-border text-left text-xs font-medium text-muted-foreground uppercase">
                                            <th className="px-4 py-3">When</th>
                                            <th className="px-4 py-3">Who</th>
                                            <th className="px-4 py-3">Action</th>
                                            <th className="px-4 py-3">
                                                Document
                                            </th>
                                            <th className="px-4 py-3">Case</th>
                                            <th className="px-4 py-3">IP</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {documentAccess.data.map((row) => (
                                            <tr
                                                key={row.id}
                                                className="border-b border-border last:border-b-0"
                                            >
                                                <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                                    {formatTime(row.accessed_at)}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {row.user}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {ACTION_LABELS[row.action] ??
                                                        row.action}
                                                </td>
                                                <td className="max-w-[240px] truncate px-4 py-3">
                                                    {row.document ?? '—'}
                                                </td>
                                                <td className="px-4 py-3 font-mono text-xs text-brand">
                                                    {row.case_reference ?? '—'}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {row.ip_address ?? '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <PaginationLinks links={documentAccess.links} />
                        </div>
                    )
                ) : null}

                {view === 'activity' && activity ? (
                    activity.data.length === 0 ? (
                        <EmptyState
                            icon={ScrollText}
                            title="No case activity yet"
                            description="Status changes, assignments, reviews and other case events will be listed here."
                        />
                    ) : (
                        <div className="overflow-hidden rounded-lg border border-border bg-card">
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[720px] text-sm">
                                    <thead>
                                        <tr className="border-b border-border text-left text-xs font-medium text-muted-foreground uppercase">
                                            <th className="px-4 py-3">When</th>
                                            <th className="px-4 py-3">Who</th>
                                            <th className="px-4 py-3">Event</th>
                                            <th className="px-4 py-3">Case</th>
                                            <th className="px-4 py-3">
                                                Patient sees
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {activity.data.map((row) => (
                                            <tr
                                                key={row.id}
                                                className="border-b border-border last:border-b-0"
                                            >
                                                <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                                    {formatTime(row.occurred_at)}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {row.actor}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <p className="font-medium">
                                                        {row.type}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {row.summary}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3 font-mono text-xs text-brand">
                                                    {row.case_reference ?? '—'}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {row.patient_visible
                                                        ? 'Yes'
                                                        : 'No'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <PaginationLinks links={activity.links} />
                        </div>
                    )
                ) : null}
            </div>
        </>
    );
}
