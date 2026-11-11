import { Head, Link, router } from '@inertiajs/react';
import { Building2, Users } from 'lucide-react';
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

type MemberRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
};

type OrganizationRow = {
    id: number;
    name: string;
    slug: string;
    members_count: number;
    departments_count: number;
    cases_count: number;
    plan_label: string;
    subscription_status: string;
    seat_limit: number | null;
    usage: {
        staff: number;
        pending_invitations: number;
        cases_total: number;
        cases_30_days: number;
        documents: number;
        storage_mb: number;
    };
    members: MemberRow[];
};

type UserRow = {
    id: number;
    name: string;
    email: string;
    is_platform_admin: boolean;
    can_impersonate: boolean;
    teams: Array<{
        id: number;
        name: string;
        slug: string;
        role: string;
        role_label: string;
    }>;
};

type Props = {
    stats: {
        organizations: number;
        users: number;
        platform_admins: number;
        cases: number;
    };
    admin_emails: string[];
    organizations: OrganizationRow[];
    users: UserRow[];
    disclaimer: string;
};

export default function PlatformDashboard({
    stats,
    admin_emails,
    organizations,
    users,
    disclaimer,
}: Props) {
    const impersonate = (userId: number) => {
        router.post(`/platform/users/${userId}/impersonate`);
    };

    return (
        <>
            <Head title="Platform admin" />
            <div className="mx-auto max-w-6xl space-y-6 p-4 md:p-8">
                <Heading
                    title="Platform admin"
                    description="Tenant inventory and audited impersonation for support/testing. Patient access still follows the impersonated user’s memberships."
                />

                <p className="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-900 dark:text-amber-100">
                    {disclaimer}
                </p>

                <section className="rounded-xl border border-border bg-card p-4 text-sm">
                    <p className="font-medium">ADMIN_EMAILS</p>
                    <p className="mt-1 text-muted-foreground">
                        {admin_emails.length > 0
                            ? admin_emails.join(', ')
                            : 'None configured — set ADMIN_EMAILS in .env or use users.is_platform_admin.'}
                    </p>
                </section>

                <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        ['Organizations', stats.organizations],
                        ['Users', stats.users],
                        ['Platform admins', stats.platform_admins],
                        ['Cases (all orgs)', stats.cases],
                    ].map(([label, value]) => (
                        <div
                            key={label}
                            className="rounded-xl border border-border bg-card p-4"
                        >
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                {label}
                            </p>
                            <p className="mt-2 font-display text-3xl font-semibold tabular-nums">
                                {value}
                            </p>
                        </div>
                    ))}
                </section>

                <section className="space-y-3">
                    <div>
                        <h2 className="font-medium text-slate-900">Users</h2>
                        <p className="text-xs text-slate-500">
                            Impersonate a role to open their dashboard, cases,
                            doctor queue, and workspace.
                        </p>
                    </div>
                    {users.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            title="No users found"
                            description="Platform users will appear here once accounts exist."
                        />
                    ) : (
                        <DataTableCard>
                            <DataTable>
                                <DataTableHeader>
                                    <DataTableHead>User</DataTableHead>
                                    <DataTableHead>
                                        Organizations / roles
                                    </DataTableHead>
                                    <DataTableHead className="text-right">
                                        Actions
                                    </DataTableHead>
                                </DataTableHeader>
                                <DataTableBody>
                                    {users.map((user) => (
                                        <DataTableRow key={user.id}>
                                            <DataTableCell>
                                                <div className="font-semibold text-slate-900">
                                                    {user.name}
                                                </div>
                                                <div className="text-xs text-slate-500">
                                                    {user.email}
                                                </div>
                                                {user.is_platform_admin ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="mt-2 rounded-full"
                                                    >
                                                        Platform admin
                                                    </Badge>
                                                ) : null}
                                            </DataTableCell>
                                            <DataTableCell>
                                                {user.teams.length === 0 ? (
                                                    <span className="text-slate-400">
                                                        No organization
                                                        membership
                                                    </span>
                                                ) : (
                                                    <ul className="space-y-1">
                                                        {user.teams.map(
                                                            (team) => (
                                                                <li
                                                                    key={
                                                                        team.id
                                                                    }
                                                                >
                                                                    <span className="font-medium text-slate-800">
                                                                        {
                                                                            team.name
                                                                        }
                                                                    </span>
                                                                    <span className="text-slate-500">
                                                                        {' '}
                                                                        ·{' '}
                                                                        {
                                                                            team.role_label
                                                                        }
                                                                    </span>
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                )}
                                            </DataTableCell>
                                            <DataTableCell className="text-right">
                                                {user.can_impersonate ? (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        className="rounded-full bg-brand text-white hover:bg-brand/90"
                                                        onClick={() =>
                                                            impersonate(
                                                                user.id,
                                                            )
                                                        }
                                                    >
                                                        Impersonate
                                                    </Button>
                                                ) : (
                                                    <span className="text-xs text-slate-400">
                                                        Not impersonatable
                                                    </span>
                                                )}
                                            </DataTableCell>
                                        </DataTableRow>
                                    ))}
                                </DataTableBody>
                            </DataTable>
                        </DataTableCard>
                    )}
                </section>

                <section className="space-y-3">
                    <h2 className="font-medium text-slate-900">
                        Organizations
                    </h2>
                    {organizations.length === 0 ? (
                        <EmptyState
                            icon={Building2}
                            title="No organizations yet"
                            description="Tenant organizations will appear here as teams are created."
                        />
                    ) : (
                        <DataTableCard>
                            <DataTable>
                                <DataTableHeader>
                                    <DataTableHead>Name</DataTableHead>
                                    <DataTableHead>Members</DataTableHead>
                                    <DataTableHead>Departments</DataTableHead>
                                    <DataTableHead>Cases</DataTableHead>
                                    <DataTableHead>Plan</DataTableHead>
                                    <DataTableHead>Usage</DataTableHead>
                                    <DataTableHead>
                                        <span className="sr-only">Manage</span>
                                    </DataTableHead>
                                </DataTableHeader>
                                <DataTableBody>
                                    {organizations.map((organization) => (
                                        <DataTableRow key={organization.id}>
                                            <DataTableCell>
                                                <div className="font-semibold text-slate-900">
                                                    {organization.name}
                                                </div>
                                                <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                                    <Badge
                                                        variant="outline"
                                                        className="rounded-full"
                                                    >
                                                        {organization.slug}
                                                    </Badge>
                                                    <span>
                                                        Impersonate a member to
                                                        open their UI
                                                    </span>
                                                </div>
                                                <ul className="mt-2 space-y-1 text-xs text-slate-500">
                                                    {organization.members.map(
                                                        (member) => (
                                                            <li key={member.id}>
                                                                {member.name} ·{' '}
                                                                {
                                                                    member.role_label
                                                                }
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            </DataTableCell>
                                            <DataTableCell className="tabular-nums text-slate-700">
                                                {organization.members_count}
                                            </DataTableCell>
                                            <DataTableCell className="tabular-nums text-slate-700">
                                                {
                                                    organization.departments_count
                                                }
                                            </DataTableCell>
                                            <DataTableCell className="tabular-nums text-slate-700">
                                                {organization.cases_count}
                                            </DataTableCell>
                                            <DataTableCell>
                                                <div className="text-sm text-slate-800">
                                                    {organization.plan_label}
                                                </div>
                                                <Badge
                                                    variant={
                                                        organization.subscription_status ===
                                                        'suspended'
                                                            ? 'destructive'
                                                            : 'secondary'
                                                    }
                                                    className="mt-1 rounded-full capitalize"
                                                >
                                                    {
                                                        organization.subscription_status
                                                    }
                                                </Badge>
                                            </DataTableCell>
                                            <DataTableCell className="text-xs text-slate-600">
                                                <div>
                                                    Seats {organization.usage.staff}
                                                    {organization.seat_limit
                                                        ? ` / ${organization.seat_limit}`
                                                        : ''}
                                                </div>
                                                <div>
                                                    {organization.usage.cases_30_days}{' '}
                                                    new cases (30d)
                                                </div>
                                                <div>
                                                    {organization.usage.documents} docs ·{' '}
                                                    {organization.usage.storage_mb} MB
                                                </div>
                                            </DataTableCell>
                                            <DataTableCell>
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link
                                                        href={`/platform/organizations/${organization.slug}`}
                                                    >
                                                        Manage
                                                    </Link>
                                                </Button>
                                            </DataTableCell>
                                        </DataTableRow>
                                    ))}
                                </DataTableBody>
                            </DataTable>
                        </DataTableCard>
                    )}
                </section>
            </div>
        </>
    );
}

PlatformDashboard.layout = {
    breadcrumbs: [{ title: 'Platform admin', href: '/platform' }],
};
