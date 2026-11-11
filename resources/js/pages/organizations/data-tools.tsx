import { Head, router, usePage } from '@inertiajs/react';
import { Download, Search, ShieldAlert, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import OrganizationTabs from '@/components/organization-tabs';

type PatientRow = {
    id: string;
    name: string;
    email: string;
    case_count: number;
};

type Props = {
    team: { id: number; name: string; slug: string };
    retentionMonths: number | null;
    expiredDocumentCount: number;
    search: string;
    patients: PatientRow[];
};

export default function DataTools({
    team,
    retentionMonths,
    expiredDocumentCount,
    search,
    patients,
}: Props) {
    const base = `/${team.slug}/organization/data`;
    const errors = usePage().props.errors as Record<string, string>;
    const [months, setMonths] = useState(
        retentionMonths === null ? '' : String(retentionMonths),
    );
    const [query, setQuery] = useState(search);
    const [erasing, setErasing] = useState<string | null>(null);
    const [confirmEmail, setConfirmEmail] = useState('');

    const saveRetention = (event: FormEvent) => {
        event.preventDefault();
        router.patch(
            `${base}/retention`,
            { document_retention_months: months === '' ? null : Number(months) },
            { preserveScroll: true },
        );
    };

    const runSearch = (event: FormEvent) => {
        event.preventDefault();
        router.get(base, { q: query || undefined }, { preserveState: true });
    };

    return (
        <>
            <Head title="Data & retention" />
            <OrganizationTabs />
            <div className="space-y-6 p-4 md:p-6">
                <header className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Data &amp; retention
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Control how long patient documents are kept, export a
                        patient&apos;s data, or erase it on request. Every action
                        is recorded on the case timeline.
                    </p>
                </header>

                <section className="space-y-4 rounded-xl border border-border bg-card p-5">
                    <div>
                        <h2 className="text-sm font-semibold">
                            Document retention
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Documents on closed or declined cases are removed
                            once the case has been idle for this many months.
                            Leave empty to keep documents until you remove them.
                        </p>
                    </div>
                    <form
                        onSubmit={saveRetention}
                        className="flex flex-wrap items-end gap-3"
                    >
                        <div className="grid gap-1.5">
                            <Label htmlFor="retention-months">
                                Keep for (months)
                            </Label>
                            <Input
                                id="retention-months"
                                type="number"
                                min={1}
                                max={240}
                                value={months}
                                onChange={(event) => setMonths(event.target.value)}
                                className="w-32"
                                placeholder="No limit"
                            />
                        </div>
                        <Button type="submit">Save policy</Button>
                    </form>
                    <InputError message={errors?.document_retention_months} />

                    {retentionMonths !== null ? (
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-muted/40 p-3 text-sm">
                            <span>
                                <strong>{expiredDocumentCount}</strong>{' '}
                                {expiredDocumentCount === 1
                                    ? 'document is'
                                    : 'documents are'}{' '}
                                past the retention period.
                            </span>
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                disabled={expiredDocumentCount === 0}
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            `Permanently delete ${expiredDocumentCount} expired document(s)? This cannot be undone.`,
                                        )
                                    ) {
                                        router.post(
                                            `${base}/purge-expired`,
                                            {},
                                            { preserveScroll: true },
                                        );
                                    }
                                }}
                            >
                                <Trash2 className="size-4" />
                                Remove expired documents
                            </Button>
                        </div>
                    ) : null}
                    <InputError message={errors?.retention} />
                </section>

                <section className="space-y-4 rounded-xl border border-border bg-card p-5">
                    <div>
                        <h2 className="text-sm font-semibold">
                            Patient data export and erasure
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Find a patient by name or email. Erasing removes
                            their personal details, documents and message text
                            but keeps the case reference and status for
                            reporting.
                        </p>
                    </div>
                    <form onSubmit={runSearch} className="flex gap-2">
                        <div className="relative max-w-md flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                placeholder="Name or email"
                                aria-label="Find a patient"
                                className="pl-9"
                            />
                        </div>
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                    </form>

                    {search !== '' && patients.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No patients match &ldquo;{search}&rdquo;.
                        </p>
                    ) : null}

                    <ul className="divide-y divide-border">
                        {patients.map((patient) => (
                            <li key={patient.id} className="space-y-3 py-3">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p className="text-sm font-medium">
                                            {patient.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {patient.email} · {patient.case_count}{' '}
                                            {patient.case_count === 1
                                                ? 'case'
                                                : 'cases'}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button asChild size="sm" variant="outline">
                                            <a
                                                href={`${base}/patients/${patient.id}/export`}
                                            >
                                                <Download className="size-4" />
                                                Export JSON
                                            </a>
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            className="text-destructive"
                                            onClick={() => {
                                                setErasing(
                                                    erasing === patient.id
                                                        ? null
                                                        : patient.id,
                                                );
                                                setConfirmEmail('');
                                            }}
                                        >
                                            <ShieldAlert className="size-4" />
                                            Erase data
                                        </Button>
                                    </div>
                                </div>
                                {erasing === patient.id ? (
                                    <form
                                        className="flex flex-wrap items-end gap-3 rounded-lg border border-destructive/30 bg-destructive/5 p-3"
                                        onSubmit={(event) => {
                                            event.preventDefault();
                                            router.post(
                                                `${base}/patients/${patient.id}/erase`,
                                                { confirm_email: confirmEmail },
                                                { preserveScroll: true },
                                            );
                                        }}
                                    >
                                        <div className="grid flex-1 gap-1.5">
                                            <Label htmlFor={`erase-${patient.id}`}>
                                                This cannot be undone. Type{' '}
                                                {patient.email} to confirm.
                                            </Label>
                                            <Input
                                                id={`erase-${patient.id}`}
                                                value={confirmEmail}
                                                onChange={(event) =>
                                                    setConfirmEmail(
                                                        event.target.value,
                                                    )
                                                }
                                                autoComplete="off"
                                            />
                                            <InputError
                                                message={errors?.confirm_email}
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={confirmEmail.trim() === ''}
                                        >
                                            Erase permanently
                                        </Button>
                                    </form>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </>
    );
}
