import { Head, Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import OrganizationTabs from '@/components/organization-tabs';

type Props = {
    team: { id: number; name: string; slug: string };
    roles: Array<{ value: string; label: string }>;
    groups: Array<{
        title: string;
        permissions: Array<{ value: string; label: string; roles: string[] }>;
    }>;
    staffUrl: string;
};

export default function Roles({ roles, groups, staffUrl }: Props) {
    return (
        <>
            <Head title="Roles and permissions" />
            <OrganizationTabs />
            <div className="space-y-5 p-4 md:p-6">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Roles and permissions
                        </h1>
                        <p className="max-w-2xl text-sm text-slate-500">
                            What each role can do. Roles are fixed by CareFlow
                            so that clinical approval is never granted by
                            accident. Assign roles to your staff from the staff
                            page.
                        </p>
                    </div>
                    <Link
                        href={staffUrl}
                        className="inline-flex min-h-9 items-center rounded-full border border-slate-200 px-4 text-sm font-medium hover:bg-slate-50"
                    >
                        Manage staff
                    </Link>
                </header>

                {groups.map((group) => (
                    <section
                        key={group.title}
                        className="overflow-hidden rounded-xl border border-slate-200 bg-white"
                    >
                        <h2 className="border-b border-slate-100 px-5 py-3 text-sm font-semibold">
                            {group.title}
                        </h2>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[720px] text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-left text-xs font-medium text-slate-500">
                                        <th className="px-5 py-2">
                                            Permission
                                        </th>
                                        {roles.map((role) => (
                                            <th
                                                key={role.value}
                                                className="px-3 py-2 text-center"
                                            >
                                                {role.label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {group.permissions.map((permission) => (
                                        <tr
                                            key={permission.value}
                                            className="border-b border-slate-100 last:border-b-0"
                                        >
                                            <td className="px-5 py-2.5">
                                                {permission.label}
                                            </td>
                                            {roles.map((role) => {
                                                const allowed =
                                                    permission.roles.includes(
                                                        role.value,
                                                    );

                                                return (
                                                    <td
                                                        key={role.value}
                                                        className="px-3 py-2.5 text-center"
                                                    >
                                                        {allowed ? (
                                                            <Check
                                                                className="mx-auto size-4 text-emerald-600"
                                                                aria-label="Allowed"
                                                            />
                                                        ) : (
                                                            <span
                                                                className="text-slate-300"
                                                                aria-label="Not allowed"
                                                            >
                                                                —
                                                            </span>
                                                        )}
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                ))}
            </div>
        </>
    );
}
