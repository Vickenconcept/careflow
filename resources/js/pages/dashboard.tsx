import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    ArrowUpRight,
    CalendarDays,
    ClipboardList,
    FileText,
    FolderHeart,
    Sparkles,
    Stethoscope,
} from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import type { DashboardInvitation } from '@/types';

const ALL = '__all__';

type Metrics = {
    available: boolean;
    definitions?: Record<string, string>;
    executive?: {
        total_enquiries: number;
        new_enquiries: number;
        active_cases: number;
        awaiting_documents: number;
        awaiting_doctor_review: number;
        awaiting_patient_response: number;
        proposals_sent: number;
        proposals_accepted: number;
        appointments_confirmed: number;
        treatments_completed: number;
        overdue_follow_ups: number;
    };
    operational?: {
        avg_first_response_hours: number | null;
        avg_document_processing_hours: number | null;
        avg_doctor_review_hours: number | null;
        avg_proposal_turnaround_hours: number | null;
        enquiry_to_proposal_conversion: number;
        proposal_to_acceptance_conversion: number;
        appointment_completion_rate: number;
        outstanding_tasks: number;
    };
    open_cases: number;
    awaiting_coordinator_review: number;
    awaiting_doctor_review: number;
    documents_needing_review: number;
    demo_cases: number;
    recent_cases: Array<{
        reference: string;
        status_label: string;
        patient_name: string;
        last_activity_at: string | null;
    }>;
    workload_by_coordinator?: Array<{
        coordinator: string;
        open_cases: number;
    }>;
};

type DashboardFilters = {
    date_from: string | null;
    date_to: string | null;
    status: string | null;
    department_id: number | null;
    country: string | null;
    language: string | null;
    coordinator_id: number | null;
    doctor_id: number | null;
};

type FilterOptions = {
    statuses: Array<{ value: string; label: string }>;
    departments: Array<{ id: number; name: string }>;
    countries: string[];
    languages: string[];
    coordinators: Array<{ id: number; name: string }>;
    doctors: Array<{ id: number; name: string }>;
};

type Props = {
    pendingInvitations?: DashboardInvitation[];
    metrics?: Metrics;
    filters?: DashboardFilters;
    filterOptions?: FilterOptions;
    team?: { id: number; name: string; slug: string } | null;
};

function greetingForHour(hour: number): string {
    if (hour < 12) {
        return 'Good morning';
    }

    if (hour < 18) {
        return 'Good afternoon';
    }

    return 'Good evening';
}

function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

function SnapshotCard({
    label,
    value,
    hint,
    tone = 'brand',
}: {
    label: string;
    value: number | string;
    hint: string;
    tone?: 'brand' | 'amber' | 'emerald' | 'rose';
}) {
    const tones = {
        brand: 'from-brand/15 via-card to-card',
        amber: 'from-amber-500/15 via-card to-card',
        emerald: 'from-emerald-500/15 via-card to-card',
        rose: 'from-rose-500/15 via-card to-card',
    };

    return (
        <div
            className={`rounded-2xl border border-border/80 bg-gradient-to-br ${tones[tone]} p-4 shadow-[0_10px_30px_-24px_rgba(15,23,42,0.35)]`}
        >
            <p className="text-[11px] font-semibold tracking-[0.12em] text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-3 font-display text-3xl font-semibold tracking-tight tabular-nums">
                {value}
            </p>
            <p className="mt-2 text-xs text-muted-foreground">{hint}</p>
        </div>
    );
}

export default function Dashboard({
    pendingInvitations = [],
    metrics,
    filters,
    filterOptions,
    team = null,
}: Props) {
    const { auth } = usePage().props;
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );
    const [showFilters, setShowFilters] = useState(false);
    const [assistantPrompt, setAssistantPrompt] = useState('');
    const [dateFrom, setDateFrom] = useState(filters?.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters?.date_to ?? '');
    const [status, setStatus] = useState(filters?.status ?? ALL);
    const [departmentId, setDepartmentId] = useState(
        filters?.department_id ? String(filters.department_id) : ALL,
    );
    const [country, setCountry] = useState(filters?.country ?? ALL);
    const [language, setLanguage] = useState(filters?.language ?? ALL);
    const [coordinatorId, setCoordinatorId] = useState(
        filters?.coordinator_id ? String(filters.coordinator_id) : ALL,
    );
    const [doctorId, setDoctorId] = useState(
        filters?.doctor_id ? String(filters.doctor_id) : ALL,
    );

    const now = useMemo(() => new Date(), []);
    const firstName = auth.user?.name?.split(' ')[0] ?? 'there';
    const greeting = `${greetingForHour(now.getHours())}, ${firstName}`;
    const executive = metrics?.executive;
    const operational = metrics?.operational;
    const casesHref = team ? `/${team.slug}/cases` : null;
    const doctorQueueHref = team ? `/${team.slug}/doctor-queue` : null;
    const workCount = metrics?.recent_cases.length ?? 0;

    const applyMetricFilters = (event: FormEvent) => {
        event.preventDefault();

        if (!team) {
            return;
        }

        router.get(
            `/${team.slug}/dashboard`,
            {
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
                status: status === ALL ? undefined : status,
                department_id: departmentId === ALL ? undefined : departmentId,
                country: country === ALL ? undefined : country,
                language: language === ALL ? undefined : language,
                coordinator_id:
                    coordinatorId === ALL ? undefined : coordinatorId,
                doctor_id: doctorId === ALL ? undefined : doctorId,
            },
            { preserveState: true, replace: true },
        );
    };

    const clearMetricFilters = () => {
        if (!team) {
            return;
        }

        setDateFrom('');
        setDateTo('');
        setStatus(ALL);
        setDepartmentId(ALL);
        setCountry(ALL);
        setLanguage(ALL);
        setCoordinatorId(ALL);
        setDoctorId(ALL);
        router.get(`/${team.slug}/dashboard`, {}, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title="Dashboard" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />

            <div className="min-h-full p-4 text-foreground md:p-6">
                <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
                    <div className="space-y-5">
                        <section className="relative overflow-hidden rounded-[28px] border border-brand/15 bg-gradient-to-br from-brand via-brand to-[#1d4ed8] p-6 text-white shadow-[0_24px_60px_-36px_rgba(47,111,237,0.85)] md:p-7">
                            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_85%_20%,rgba(255,255,255,0.28),transparent_34%)]" />
                            <div className="pointer-events-none absolute -right-8 -bottom-10 size-48 rounded-full bg-white/10 blur-2xl" />
                            <div className="relative grid items-center gap-6 lg:grid-cols-[minmax(0,1fr)_220px]">
                                <div className="max-w-2xl space-y-4">
                                    <Badge className="border-white/20 bg-white/15 text-white">
                                        Care operations
                                    </Badge>
                                    <div>
                                        <h1 className="font-display text-3xl font-semibold tracking-tight md:text-4xl">
                                            {greeting}
                                        </h1>
                                        <p className="mt-2 text-sm text-white/80">
                                            {team
                                                ? `Keep ${team.name} moving — triage cases, clear reviews, and follow up before patients wait.`
                                                : 'Select an organization to unlock live operations.'}
                                        </p>
                                    </div>
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                        <div className="flex h-12 flex-1 items-center gap-2 rounded-2xl bg-white px-3 text-foreground shadow-sm">
                                            <Sparkles className="size-4 text-brand" />
                                            <input
                                                value={assistantPrompt}
                                                onChange={(event) =>
                                                    setAssistantPrompt(
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Ask CareFlow about backlog, doctor review, follow-ups..."
                                                className="h-full flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                                            />
                                            <Button
                                                type="button"
                                                size="icon"
                                                className="size-9 rounded-xl bg-brand text-brand-foreground hover:bg-brand/90"
                                            >
                                                <ArrowRight className="size-4" />
                                            </Button>
                                        </div>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {[
                                            'Doctor review backlog',
                                            'Overdue follow-ups',
                                            'Conversion snapshot',
                                        ].map((prompt) => (
                                            <button
                                                key={prompt}
                                                type="button"
                                                onClick={() =>
                                                    setAssistantPrompt(prompt)
                                                }
                                                className="rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs text-white/90 hover:bg-white/20"
                                            >
                                                {prompt}
                                            </button>
                                        ))}
                                    </div>
                                    {metrics?.demo_cases ? (
                                        <p className="inline-flex items-center gap-2 text-xs text-white/75">
                                            <AlertTriangle className="size-3.5" />
                                            {metrics.demo_cases} demo cases
                                            included in totals
                                        </p>
                                    ) : null}
                                </div>

                                <div className="relative mx-auto hidden w-full max-w-[220px] justify-self-end lg:block">
                                    <div className="absolute inset-x-6 bottom-2 h-10 rounded-full bg-black/20 blur-xl" />
                                    <img
                                        src="/images/doctor-avatar.svg"
                                        alt=""
                                        className="relative mx-auto h-52 w-auto drop-shadow-[0_18px_30px_rgba(0,0,0,0.28)]"
                                    />
                                </div>
                            </div>
                        </section>

                        {metrics?.available ? (
                            <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                <SnapshotCard
                                    label="Active cases"
                                    value={
                                        executive?.active_cases ??
                                        metrics.open_cases
                                    }
                                    hint="Open caseload"
                                    tone="brand"
                                />
                                <SnapshotCard
                                    label="Coordinator review"
                                    value={metrics.awaiting_coordinator_review}
                                    hint="Needs triage"
                                    tone="amber"
                                />
                                <SnapshotCard
                                    label="Doctor review"
                                    value={metrics.awaiting_doctor_review}
                                    hint="Clinical backlog"
                                    tone="emerald"
                                />
                                <SnapshotCard
                                    label="Overdue follow-ups"
                                    value={executive?.overdue_follow_ups ?? 0}
                                    hint="Past due actions"
                                    tone="rose"
                                />
                            </section>
                        ) : null}

                        {metrics?.available && team && filterOptions ? (
                            <section className="rounded-2xl border border-border/80 bg-card p-4 shadow-[0_10px_30px_-24px_rgba(15,23,42,0.25)]">
                                <div className="mb-3 flex items-center justify-between gap-3">
                                    <div>
                                        <h2 className="text-sm font-semibold">
                                            Analytics filters
                                        </h2>
                                        <p className="text-xs text-muted-foreground">
                                            Narrow KPIs by date, status, and
                                            ownership
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            setShowFilters((value) => !value)
                                        }
                                    >
                                        {showFilters ? 'Hide' : 'Show'}
                                    </Button>
                                </div>
                                {showFilters ? (
                                    <form
                                        className="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
                                        onSubmit={applyMetricFilters}
                                    >
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="date_from">
                                                From
                                            </Label>
                                            <Input
                                                id="date_from"
                                                type="date"
                                                value={dateFrom}
                                                onChange={(event) =>
                                                    setDateFrom(
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="date_to">To</Label>
                                            <Input
                                                id="date_to"
                                                type="date"
                                                value={dateTo}
                                                onChange={(event) =>
                                                    setDateTo(event.target.value)
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label>Status</Label>
                                            <Select
                                                value={status}
                                                onValueChange={setStatus}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="All statuses" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value={ALL}>
                                                        All statuses
                                                    </SelectItem>
                                                    {filterOptions.statuses.map(
                                                        (option) => (
                                                            <SelectItem
                                                                key={
                                                                    option.value
                                                                }
                                                                value={
                                                                    option.value
                                                                }
                                                            >
                                                                {option.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label>Department</Label>
                                            <Select
                                                value={departmentId}
                                                onValueChange={setDepartmentId}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="All departments" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value={ALL}>
                                                        All departments
                                                    </SelectItem>
                                                    {filterOptions.departments.map(
                                                        (department) => (
                                                            <SelectItem
                                                                key={
                                                                    department.id
                                                                }
                                                                value={String(
                                                                    department.id,
                                                                )}
                                                            >
                                                                {
                                                                    department.name
                                                                }
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label>Country</Label>
                                            <Select
                                                value={country}
                                                onValueChange={setCountry}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="All countries" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value={ALL}>
                                                        All countries
                                                    </SelectItem>
                                                    {filterOptions.countries.map(
                                                        (value) => (
                                                            <SelectItem
                                                                key={value}
                                                                value={value}
                                                            >
                                                                {value}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label>Language</Label>
                                            <Select
                                                value={language}
                                                onValueChange={setLanguage}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="All languages" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value={ALL}>
                                                        All languages
                                                    </SelectItem>
                                                    {filterOptions.languages.map(
                                                        (value) => (
                                                            <SelectItem
                                                                key={value}
                                                                value={value}
                                                            >
                                                                {value.toUpperCase()}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label>Coordinator</Label>
                                            <Select
                                                value={coordinatorId}
                                                onValueChange={setCoordinatorId}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="All coordinators" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value={ALL}>
                                                        All coordinators
                                                    </SelectItem>
                                                    {filterOptions.coordinators.map(
                                                        (person) => (
                                                            <SelectItem
                                                                key={person.id}
                                                                value={String(
                                                                    person.id,
                                                                )}
                                                            >
                                                                {person.name}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label>Doctor</Label>
                                            <Select
                                                value={doctorId}
                                                onValueChange={setDoctorId}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="All doctors" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value={ALL}>
                                                        All doctors
                                                    </SelectItem>
                                                    {filterOptions.doctors.map(
                                                        (person) => (
                                                            <SelectItem
                                                                key={person.id}
                                                                value={String(
                                                                    person.id,
                                                                )}
                                                            >
                                                                {person.name}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="flex items-end gap-2 md:col-span-2 xl:col-span-4">
                                            <Button type="submit">
                                                Apply filters
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={clearMetricFilters}
                                            >
                                                Clear
                                            </Button>
                                        </div>
                                    </form>
                                ) : null}
                            </section>
                        ) : null}

                        <section className="rounded-2xl border border-border/80 bg-card p-5 shadow-[0_10px_30px_-24px_rgba(15,23,42,0.25)]">
                            <div className="mb-4 flex items-center justify-between gap-3">
                                <div className="flex items-center gap-2">
                                    <h2 className="font-display text-lg font-semibold">
                                        Work today
                                    </h2>
                                    <Badge className="bg-brand/15 text-brand">
                                        {workCount}
                                    </Badge>
                                </div>
                                {casesHref ? (
                                    <Link
                                        href={casesHref}
                                        className="inline-flex items-center gap-1 text-sm font-medium text-brand hover:underline"
                                    >
                                        View all
                                        <ArrowUpRight className="size-3.5" />
                                    </Link>
                                ) : null}
                            </div>

                            {!metrics?.available ? (
                                <EmptyState
                                    icon={FolderHeart}
                                    title="No organization selected"
                                    description="Select an organization with case access to see live work."
                                    className="py-10 shadow-none"
                                />
                            ) : metrics.recent_cases.length === 0 ? (
                                <EmptyState
                                    icon={ClipboardList}
                                    title="No recent case activity"
                                    description="New enquiries and case updates will show up here as work comes in."
                                    className="py-10 shadow-none"
                                />
                            ) : (
                                <ul className="space-y-2.5">
                                    {metrics.recent_cases.map((item) => (
                                        <li
                                            key={item.reference}
                                            className="flex items-center gap-3 rounded-xl border border-border/80 bg-surface/80 px-3 py-3 transition hover:border-brand/25"
                                        >
                                            <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand/10 text-xs font-semibold text-brand">
                                                {initials(item.patient_name)}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                {team ? (
                                                    <Link
                                                        href={`/${team.slug}/cases/${item.reference}`}
                                                        className="font-medium text-foreground hover:text-brand"
                                                    >
                                                        {item.patient_name}
                                                    </Link>
                                                ) : (
                                                    <span className="font-medium">
                                                        {item.patient_name}
                                                    </span>
                                                )}
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {item.reference} ·{' '}
                                                    {item.status_label}
                                                </p>
                                            </div>
                                            <FolderHeart className="size-4 text-muted-foreground" />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </div>

                    <aside className="space-y-4">
                        <section className="rounded-2xl border border-border/80 bg-card p-4 shadow-[0_10px_30px_-24px_rgba(15,23,42,0.25)]">
                            <div className="mb-3 flex items-center justify-between">
                                <h2 className="text-sm font-semibold">
                                    Follow-ups
                                </h2>
                                <CalendarDays className="size-4 text-muted-foreground" />
                            </div>
                            {(executive?.overdue_follow_ups ?? 0) > 0 ? (
                                <div className="rounded-xl border border-rose-500/20 bg-rose-500/10 px-3 py-3">
                                    <p className="text-2xl font-semibold tabular-nums text-rose-600 dark:text-rose-300">
                                        {executive?.overdue_follow_ups}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Overdue follow-ups need attention
                                    </p>
                                </div>
                            ) : (
                                <p className="rounded-xl border border-dashed border-border px-3 py-6 text-center text-sm text-muted-foreground">
                                    No overdue follow-ups right now
                                </p>
                            )}
                        </section>

                        <section className="rounded-2xl border border-border/80 bg-card p-4 shadow-[0_10px_30px_-24px_rgba(15,23,42,0.25)]">
                            <div className="mb-3 flex items-center justify-between">
                                <h2 className="text-sm font-semibold">Tasks</h2>
                                <Badge
                                    variant="secondary"
                                    className="bg-rose-500/10 text-rose-600 dark:text-rose-300"
                                >
                                    {operational?.outstanding_tasks ?? 0} open
                                </Badge>
                            </div>
                            <ul className="space-y-2.5">
                                <li className="flex items-start gap-3 rounded-xl border border-border/70 px-3 py-2.5">
                                    <Stethoscope className="mt-0.5 size-4 text-brand" />
                                    <div>
                                        <p className="text-sm font-medium">
                                            Clear doctor review backlog
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {metrics?.awaiting_doctor_review ??
                                                0}{' '}
                                            waiting
                                        </p>
                                    </div>
                                </li>
                                <li className="flex items-start gap-3 rounded-xl border border-border/70 px-3 py-2.5">
                                    <FileText className="mt-0.5 size-4 text-amber-500" />
                                    <div>
                                        <p className="text-sm font-medium">
                                            Review documents
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {metrics?.documents_needing_review ??
                                                0}{' '}
                                            need human review
                                        </p>
                                    </div>
                                </li>
                                <li className="flex items-start gap-3 rounded-xl border border-border/70 px-3 py-2.5">
                                    <ClipboardList className="mt-0.5 size-4 text-emerald-500" />
                                    <div>
                                        <p className="text-sm font-medium">
                                            Coordinator triage
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {metrics?.awaiting_coordinator_review ??
                                                0}{' '}
                                            awaiting review
                                        </p>
                                    </div>
                                </li>
                            </ul>
                            {doctorQueueHref ? (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="mt-3 w-full"
                                >
                                    <Link href={doctorQueueHref}>
                                        Open doctor queue
                                    </Link>
                                </Button>
                            ) : null}
                        </section>

                        <section className="rounded-2xl border border-border/80 bg-card p-4 shadow-[0_10px_30px_-24px_rgba(15,23,42,0.25)]">
                            <div className="mb-3 flex items-center justify-between">
                                <h2 className="text-sm font-semibold">
                                    Overview
                                </h2>
                                <span className="text-[11px] text-muted-foreground">
                                    Live
                                </span>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                <span className="font-semibold text-foreground">
                                    {executive?.total_enquiries ?? 0}
                                </span>{' '}
                                enquiries ·{' '}
                                <span className="font-semibold text-foreground">
                                    {executive?.proposals_sent ?? 0}
                                </span>{' '}
                                proposals sent
                            </p>
                            <div className="mt-4 space-y-2">
                                <div className="flex items-center justify-between text-xs">
                                    <span className="text-muted-foreground">
                                        Enquiry → proposal
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {operational?.enquiry_to_proposal_conversion ??
                                            0}
                                        %
                                    </span>
                                </div>
                                <div className="h-2 overflow-hidden rounded-full bg-muted">
                                    <div
                                        className="h-full rounded-full bg-brand"
                                        style={{
                                            width: `${Math.min(operational?.enquiry_to_proposal_conversion ?? 0, 100)}%`,
                                        }}
                                    />
                                </div>
                                <div className="flex items-center justify-between text-xs">
                                    <span className="text-muted-foreground">
                                        Proposal → acceptance
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {operational?.proposal_to_acceptance_conversion ??
                                            0}
                                        %
                                    </span>
                                </div>
                                <div className="h-2 overflow-hidden rounded-full bg-muted">
                                    <div
                                        className="h-full rounded-full bg-emerald-500"
                                        style={{
                                            width: `${Math.min(operational?.proposal_to_acceptance_conversion ?? 0, 100)}%`,
                                        }}
                                    />
                                </div>
                            </div>
                        </section>
                    </aside>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
    ],
});
