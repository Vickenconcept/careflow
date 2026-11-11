import { Head, Link, router } from '@inertiajs/react';
import { FolderOpen, Languages, Mail, MapPin, Phone, Search, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
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
import {
    PaginationLinks,
    type PaginationLink,
} from '@/components/pagination-links';

type PatientRow = {
    id: string;
    name: string;
    email: string;
    phone: string | null;
    country: string | null;
    language: string | null;
    case_count: number;
    cases: Array<{ reference: string; status_label: string; url: string }>;
    last_activity_at: string | null;
};

type Props = {
    team: { id: number; name: string; slug: string };
    patients: {
        data: PatientRow[];
        links: PaginationLink[];
        total?: number;
    };
    search: string;
};

export default function Patients({ team, patients, search }: Props) {
    const [term, setTerm] = useState(search);

    useEffect(() => {
        if (term === search) {
            return;
        }

        const handle = window.setTimeout(() => {
            router.get(
                `/${team.slug}/patients`,
                { q: term || undefined },
                { preserveState: true, replace: true },
            );
        }, 350);

        return () => window.clearTimeout(handle);
    }, [term, search, team.slug]);

    return (
        <>
            <Head title="Patients" />
            <div className="space-y-5 p-4 md:p-6">
                <header className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Patients
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {patients.total ?? patients.data.length}{' '}
                        {(patients.total ?? patients.data.length) === 1
                            ? 'patient'
                            : 'patients'}{' '}
                        across the cases you can open.
                    </p>
                </header>

                <div className="relative max-w-md">
                    <Search
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <input
                        type="search"
                        value={term}
                        onChange={(event) => setTerm(event.target.value)}
                        aria-label="Search patients"
                        placeholder="Search name, email or phone"
                        className="h-10 w-full rounded-lg border border-border bg-card pr-3 pl-9 text-sm outline-none focus:ring-2 focus:ring-brand/30"
                    />
                </div>

                {patients.data.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title={
                            search
                                ? 'No patients match your search'
                                : 'No patients yet'
                        }
                        description={
                            search
                                ? 'Try a different name, email address or phone number.'
                                : 'Patients appear here after they submit an enquiry.'
                        }
                    />
                ) : (
                    <DataTableCard>
                        <DataTable>
                            <DataTableHeader>
                                <DataTableHead>Patient</DataTableHead>
                                <DataTableHead>Country</DataTableHead>
                                <DataTableHead>Language</DataTableHead>
                                <DataTableHead>Cases</DataTableHead>
                                <DataTableHead>Last activity</DataTableHead>
                                <DataTableHead className="text-right">
                                    Actions
                                </DataTableHead>
                            </DataTableHeader>
                            <DataTableBody>
                                {patients.data.map((patient) => (
                                    <DataTableRow key={patient.id}>
                                        <DataTableCell>
                                            <div className="flex items-center gap-3">
                                                <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand/10 text-xs font-semibold text-brand">
                                                    {patient.name
                                                        .split(' ')
                                                        .map((part) => part[0])
                                                        .join('')
                                                        .slice(0, 2)
                                                        .toUpperCase()}
                                                </span>
                                                <div className="min-w-0">
                                                    <p className="truncate font-semibold text-slate-900">
                                                        {patient.name}
                                                    </p>
                                                    <p className="truncate text-xs text-slate-500">
                                                        {patient.email}
                                                        {patient.phone
                                                            ? ` · ${patient.phone}`
                                                            : ''}
                                                    </p>
                                                </div>
                                            </div>
                                        </DataTableCell>
                                        <DataTableCell className="text-slate-700">
                                            {patient.country ? (
                                                <span className="inline-flex items-center gap-1.5">
                                                    <MapPin className="size-3.5 text-slate-400" />
                                                    {patient.country}
                                                </span>
                                            ) : (
                                                '—'
                                            )}
                                        </DataTableCell>
                                        <DataTableCell className="text-slate-700 uppercase">
                                            {patient.language ? (
                                                <span className="inline-flex items-center gap-1.5">
                                                    <Languages className="size-3.5 text-slate-400" />
                                                    {patient.language}
                                                </span>
                                            ) : (
                                                '—'
                                            )}
                                        </DataTableCell>
                                        <DataTableCell>
                                            <ul className="space-y-1">
                                                {patient.cases.map((item) => (
                                                    <li key={item.reference}>
                                                        <Link
                                                            href={item.url}
                                                            className="font-mono text-xs font-medium text-brand hover:underline"
                                                        >
                                                            {item.reference}
                                                        </Link>
                                                        <span className="ms-2 text-xs text-slate-500">
                                                            {item.status_label}
                                                        </span>
                                                    </li>
                                                ))}
                                                {patient.case_count >
                                                patient.cases.length ? (
                                                    <li className="text-xs text-slate-500">
                                                        +
                                                        {patient.case_count -
                                                            patient.cases.length}{' '}
                                                        more
                                                    </li>
                                                ) : null}
                                            </ul>
                                        </DataTableCell>
                                        <DataTableCell className="whitespace-nowrap text-slate-500">
                                            {patient.last_activity_at
                                                ? new Date(
                                                      patient.last_activity_at,
                                                  ).toLocaleDateString()
                                                : '—'}
                                        </DataTableCell>
                                        <DataTableCell>
                                            <RowActions>
                                                <RowAction
                                                    label="Open latest case"
                                                    icon={FolderOpen}
                                                    href={patient.cases[0]?.url}
                                                    disabled={!patient.cases[0]}
                                                    tone="primary"
                                                    showLabel
                                                />
                                                <RowAction
                                                    label="Email patient"
                                                    icon={Mail}
                                                    nativeHref={`mailto:${patient.email}`}
                                                />
                                                <RowAction
                                                    label="Call patient"
                                                    icon={Phone}
                                                    nativeHref={
                                                        patient.phone
                                                            ? `tel:${patient.phone}`
                                                            : undefined
                                                    }
                                                    disabled={!patient.phone}
                                                />
                                            </RowActions>
                                        </DataTableCell>
                                    </DataTableRow>
                                ))}
                            </DataTableBody>
                        </DataTable>                        <PaginationLinks links={patients.links} />
                    </DataTableCard>
                )}
            </div>
        </>
    );
}
