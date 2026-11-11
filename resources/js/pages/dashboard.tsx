import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    CalendarCheck,
    ChevronRight,
    ClipboardCheck,
    FileText,
    FolderHeart,
    HeartPulse,
    Hourglass,
    Plus,
    Search,
    SendHorizontal,
    SlidersHorizontal,
    Sparkles,
    Stethoscope,
    UserRound,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
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

/** Sage-green palette taken from the design reference. */
const card =
    'rounded-[28px] bg-[#fbfbf9] p-5 shadow-[0_1px_0_rgba(18,40,28,0.04)] dark:bg-[#151f18] md:p-6';
const tile = 'rounded-[22px] bg-[#f1f2ee] dark:bg-[#1c2820]';
const ink = 'text-[#12281c] dark:text-[#e9f0e5]';
const soft = 'text-[#6b7a70] dark:text-[#9bab9f]';

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

function percent(part: number, whole: number): number {
    return whole > 0 ? Math.min(100, Math.round((part / whole) * 100)) : 0;
}

function formatWhen(value: string | null): { top: string; bottom: string } {
    if (!value) {
        return { top: '—', bottom: '' };
    }

    const date = new Date(value);
    const sameDay = date.toDateString() === new Date().toDateString();

    return {
        top: sameDay
            ? date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            : date.toLocaleDateString([], { day: 'numeric', month: 'short' }),
        bottom: sameDay ? 'Today' : '',
    };
}

function CardHeader({
    title,
    count,
    action,
}: {
    title: string;
    count?: number;
    action?: ReactNode;
}) {
    return (
        <div className="mb-4 flex items-center justify-between gap-3">
            <div className="flex items-center gap-2.5">
                <h2 className={`text-lg font-semibold tracking-tight ${ink}`}>
                    {title}
                </h2>
                {count !== undefined ? (
                    <span className="flex size-6 items-center justify-center rounded-full bg-[#e4ecd9] text-xs font-semibold text-[#3f6a2a] dark:bg-[#2a3a22] dark:text-[#b5d49a]">
                        {count}
                    </span>
                ) : null}
            </div>
            {action}
        </div>
    );
}

function StatTile({
    icon: Icon,
    label,
    value,
    of,
    alert = false,
    href,
}: {
    icon: LucideIcon;
    label: string;
    value: number;
    of?: number;
    alert?: boolean;
    href?: string | null;
}) {
    const body = (
        <div
            className={`${tile} flex h-full flex-col items-center px-3 py-5 text-center transition hover:-translate-y-0.5`}
        >
            <span
                className={`flex size-11 items-center justify-center rounded-full border bg-white dark:bg-[#151f18] ${
                    alert && value > 0
                        ? 'border-[#e8b4a8] text-[#c2553d]'
                        : 'border-[#d5dfc6] text-[#5f7d45] dark:border-[#3a4d33] dark:text-[#a9c58a]'
                }`}
            >
                <Icon className="size-5" />
            </span>
            <p className={`mt-3 text-[13px] leading-tight font-medium ${ink}`}>
                {label}
            </p>
            <p className={`mt-2 text-2xl font-semibold tabular-nums ${ink}`}>
                {value}
                {of !== undefined && of > 0 ? (
                    <span className={`text-sm font-medium ${soft}`}>
                        {' '}
                        / {of}
                    </span>
                ) : null}
            </p>
        </div>
    );

    return href ? (
        <Link href={href} className="block">
            {body}
        </Link>
    ) : (
        body
    );
}

function JourneyBar({
    total,
    sent,
    accepted,
}: {
    total: number;
    sent: number;
    accepted: number;
}) {
    const sentPct = percent(sent, total);
    const acceptedPct = Math.min(percent(accepted, total), sentPct);

    const pin = (position: number, label: string) => (
        <div
            className="absolute -top-1 flex -translate-x-1/2 flex-col items-center"
            style={{ left: `${Math.min(Math.max(position, 4), 96)}%` }}
        >
            <span className="rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold text-[#12281c] shadow-sm dark:bg-[#1c2820] dark:text-[#e9f0e5]">
                {label}
            </span>
            <span className="h-4 w-px bg-[#12281c] dark:bg-[#9bab9f]" />
            <span className="size-1.5 rounded-full bg-[#12281c] dark:bg-[#9bab9f]" />
        </div>
    );

    return (
        <div>
            <div className="flex items-baseline justify-between">
                <p className={`text-[15px] font-medium ${ink}`}>
                    Enquiry journey
                </p>
                <p className={`text-xs ${soft}`}>{total} enquiries</p>
            </div>
            <div className="relative mt-2 pt-9">
                {pin(acceptedPct, `Accepted ${acceptedPct}%`)}
                {sentPct > acceptedPct + 18
                    ? pin(sentPct, `Proposed ${sentPct}%`)
                    : null}
                <div className="relative h-4 overflow-hidden rounded-sm bg-[repeating-linear-gradient(135deg,#dfe4d8_0,#dfe4d8_3px,#f1f3ee_3px,#f1f3ee_6px)] dark:bg-[repeating-linear-gradient(135deg,#2a372d_0,#2a372d_3px,#1c2820_3px,#1c2820_6px)]">
                    <div
                        className="absolute inset-y-0 left-0 bg-[#9bb577]"
                        style={{ width: `${sentPct}%` }}
                    />
                    <div
                        className="absolute inset-y-0 left-0 bg-[#6f8f4e]"
                        style={{ width: `${acceptedPct}%` }}
                    />
                </div>
                <div
                    className={`mt-1.5 flex justify-between text-[10px] ${soft}`}
                >
                    <span>0%</span>
                    <span>100%</span>
                </div>
            </div>
        </div>
    );
}

function Donut({
    segments,
}: {
    segments: Array<{ label: string; value: number; color: string }>;
}) {
    const total = segments.reduce((sum, item) => sum + item.value, 0);
    const radius = 62;
    const circumference = 2 * Math.PI * radius;
    let offset = 0;

    return (
        <div className="flex h-full flex-col items-center justify-between gap-4">
            <div className="relative size-44 shrink-0">
                <svg viewBox="0 0 160 160" className="size-full -rotate-90">
                    <circle
                        cx="80"
                        cy="80"
                        r={radius}
                        fill="none"
                        stroke="rgba(255,255,255,0.55)"
                        strokeWidth="26"
                    />
                    {total > 0
                        ? segments.map((segment) => {
                              const length =
                                  (segment.value / total) * circumference;
                              const dash = `${Math.max(length - 3, 0)} ${circumference - Math.max(length - 3, 0)}`;
                              const node = (
                                  <circle
                                      key={segment.label}
                                      cx="80"
                                      cy="80"
                                      r={radius}
                                      fill="none"
                                      stroke={segment.color}
                                      strokeWidth="26"
                                      strokeDasharray={dash}
                                      strokeDashoffset={-offset}
                                  />
                              );

                              offset += length;

                              return node;
                          })
                        : null}
                </svg>
                <div className="absolute inset-0 flex flex-col items-center justify-center text-[#12281c]">
                    <span className="text-3xl font-semibold tabular-nums">
                        {total}
                    </span>
                    <span className="text-[11px] font-medium text-[#4d6a3a]">
                        cases in motion
                    </span>
                </div>
            </div>
            <ul className="grid w-full grid-cols-2 gap-x-3 gap-y-1.5 text-[11px] text-[#26412f]">
                {segments.map((segment) => (
                    <li key={segment.label} className="flex items-center gap-1.5">
                        <span
                            className="size-2 shrink-0 rounded-sm"
                            style={{ background: segment.color }}
                        />
                        <span className="truncate">{segment.label}</span>
                        <span className="ml-auto font-semibold tabular-nums">
                            {segment.value}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function FunnelBars({ values }: { values: Array<{ label: string; value: number }> }) {
    const max = Math.max(...values.map((item) => item.value), 1);

    return (
        <div className="flex h-20 items-end gap-1.5" role="img" aria-label="Case funnel">
            {values.map((item, index) => (
                <div
                    key={item.label}
                    title={`${item.label}: ${item.value}`}
                    className="flex-1 rounded-t-md"
                    style={{
                        height: `${Math.max((item.value / max) * 100, 8)}%`,
                        background: ['#d9e3c8', '#c5d4ab', '#aec58a', '#93b06b', '#7a9a55', '#5f7d45'][index],
                    }}
                />
            ))}
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
    const [quickFind, setQuickFind] = useState('');
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
    const executive = metrics?.executive;
    const operational = metrics?.operational;
    const slug = team?.slug ?? null;
    const casesHref = slug ? `/${slug}/cases` : null;
    const doctorQueueHref = slug ? `/${slug}/doctor-queue` : null;
    const totalEnquiries = executive?.total_enquiries ?? 0;
    const today = now.toLocaleDateString([], {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    const flow = [
        { label: 'Awaiting documents', value: executive?.awaiting_documents ?? 0, color: '#6f8f4e' },
        { label: 'Doctor review', value: metrics?.awaiting_doctor_review ?? 0, color: '#e2b93d' },
        { label: 'Coordinator review', value: metrics?.awaiting_coordinator_review ?? 0, color: '#d6b3e3' },
        { label: 'Patient reply', value: executive?.awaiting_patient_response ?? 0, color: '#a8c07f' },
    ];

    const funnel = [
        { label: 'Enquiries', value: totalEnquiries },
        { label: 'Active cases', value: executive?.active_cases ?? 0 },
        { label: 'Proposals sent', value: executive?.proposals_sent ?? 0 },
        { label: 'Proposals accepted', value: executive?.proposals_accepted ?? 0 },
        { label: 'Appointments confirmed', value: executive?.appointments_confirmed ?? 0 },
        { label: 'Treatments completed', value: executive?.treatments_completed ?? 0 },
    ];

    const shortcuts: Array<{ label: string; href: string | null; keywords: string[] }> = [
        { label: 'Doctor review backlog', href: doctorQueueHref, keywords: ['doctor', 'review'] },
        { label: 'Overdue follow-ups', href: slug ? `/${slug}/work` : null, keywords: ['follow', 'overdue', 'task'] },
        { label: 'Unassigned cases', href: slug ? `/${slug}/cases?assignment=unassigned` : null, keywords: ['unassigned'] },
    ];

    const goQuickFind = (event: FormEvent) => {
        event.preventDefault();

        const text = quickFind.trim();

        if (!slug || text === '') {
            return;
        }

        const match = shortcuts.find((item) =>
            item.keywords.some((keyword) => text.toLowerCase().includes(keyword)),
        );

        if (match?.href) {
            router.visit(match.href);

            return;
        }

        router.visit(`/${slug}/cases`, { data: { search: text } });
    };

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

    const filterSelect = (
        label: string,
        value: string,
        onChange: (value: string) => void,
        placeholder: string,
        options: Array<{ value: string; label: string }>,
    ) => (
        <div className="grid gap-1.5">
            <Label>{label}</Label>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger>
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>{placeholder}</SelectItem>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );

    return (
        <>
            <Head title="Dashboard" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />

            <div className="space-y-5 p-4 md:p-6">
                <div className="space-y-5">
                    {/* Heading */}
                    <header className="flex flex-wrap items-end justify-between gap-4 pt-2">
                        <div>
                            <h1
                                className={`font-display text-4xl font-medium tracking-tight md:text-6xl ${ink}`}
                            >
                                {greetingForHour(now.getHours())}, {firstName}
                            </h1>
                            <p className={`mt-3 max-w-xl text-sm ${soft}`}>
                                {team
                                    ? 'Triage new enquiries, clear reviews and follow up before patients wait, all from one place.'
                                    : 'Select an organization to unlock live operations.'}
                            </p>
                            {metrics?.demo_cases ? (
                                <p className="mt-3 inline-flex items-center gap-2 rounded-full bg-[#f4e9c8] px-3 py-1 text-xs font-medium text-[#7a5d12]">
                                    <AlertTriangle className="size-3.5" />
                                    {metrics.demo_cases} demo cases included in
                                    totals
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-col items-end gap-3 text-right">
                            {team ? (
                                <p className={`inline-flex items-center gap-2 text-sm font-medium ${ink}`}>
                                    <Building2 className="size-4" />
                                    {team.name}
                                </p>
                            ) : null}
                            <p className={`text-xs ${soft}`}>{today}</p>
                            {metrics?.available && team && filterOptions ? (
                                <button
                                    type="button"
                                    onClick={() => setShowFilters((value) => !value)}
                                    className="inline-flex items-center gap-2 rounded-full bg-[#12281c] px-4 py-2 text-xs font-medium text-white transition hover:bg-[#1c3a29] dark:bg-[#6f8f4e] dark:text-[#0e1610]"
                                >
                                    <SlidersHorizontal className="size-3.5" />
                                    {showFilters ? 'Hide filters' : 'Filter numbers'}
                                </button>
                            ) : null}
                        </div>
                    </header>

                    {showFilters && metrics?.available && team && filterOptions ? (
                        <section className={card}>
                            <CardHeader title="Filter the numbers" />
                            <form
                                className="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
                                onSubmit={applyMetricFilters}
                            >
                                <div className="grid gap-1.5">
                                    <Label htmlFor="date_from">From</Label>
                                    <Input
                                        id="date_from"
                                        type="date"
                                        value={dateFrom}
                                        onChange={(event) => setDateFrom(event.target.value)}
                                    />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="date_to">To</Label>
                                    <Input
                                        id="date_to"
                                        type="date"
                                        value={dateTo}
                                        onChange={(event) => setDateTo(event.target.value)}
                                    />
                                </div>
                                {filterSelect('Status', status, setStatus, 'All statuses', filterOptions.statuses)}
                                {filterSelect(
                                    'Department',
                                    departmentId,
                                    setDepartmentId,
                                    'All departments',
                                    filterOptions.departments.map((item) => ({ value: String(item.id), label: item.name })),
                                )}
                                {filterSelect(
                                    'Country',
                                    country,
                                    setCountry,
                                    'All countries',
                                    filterOptions.countries.map((value) => ({ value, label: value })),
                                )}
                                {filterSelect(
                                    'Language',
                                    language,
                                    setLanguage,
                                    'All languages',
                                    filterOptions.languages.map((value) => ({ value, label: value.toUpperCase() })),
                                )}
                                {filterSelect(
                                    'Coordinator',
                                    coordinatorId,
                                    setCoordinatorId,
                                    'All coordinators',
                                    filterOptions.coordinators.map((item) => ({ value: String(item.id), label: item.name })),
                                )}
                                {filterSelect(
                                    'Doctor',
                                    doctorId,
                                    setDoctorId,
                                    'All doctors',
                                    filterOptions.doctors.map((item) => ({ value: String(item.id), label: item.name })),
                                )}
                                <div className="flex items-end gap-2 md:col-span-2 xl:col-span-4">
                                    <Button type="submit" className="rounded-full bg-[#12281c] hover:bg-[#1c3a29]">
                                        Apply filters
                                    </Button>
                                    <Button type="button" variant="outline" className="rounded-full" onClick={clearMetricFilters}>
                                        Clear
                                    </Button>
                                </div>
                            </form>
                        </section>
                    ) : null}

                    {!metrics?.available ? (
                        <section className={card}>
                            <EmptyState
                                icon={FolderHeart}
                                title="No organization selected"
                                description="Select an organization with case access to see live work."
                                className="py-10 shadow-none"
                            />
                        </section>
                    ) : (
                        <>
                            {/* Row 1 */}
                            <div className="grid gap-5 xl:grid-cols-[1.6fr_1fr_1fr]">
                                <section className={card}>
                                    <CardHeader title="Case overview" />
                                    <JourneyBar
                                        total={totalEnquiries}
                                        sent={executive?.proposals_sent ?? 0}
                                        accepted={executive?.proposals_accepted ?? 0}
                                    />
                                    <div className="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                                        <StatTile
                                            icon={Users}
                                            label="Active cases"
                                            value={executive?.active_cases ?? metrics.open_cases}
                                            of={totalEnquiries}
                                            href={casesHref}
                                        />
                                        <StatTile
                                            icon={ClipboardCheck}
                                            label="Coordinator review"
                                            value={metrics.awaiting_coordinator_review}
                                            href={casesHref}
                                        />
                                        <StatTile
                                            icon={Stethoscope}
                                            label="Doctor review"
                                            value={metrics.awaiting_doctor_review}
                                            href={doctorQueueHref}
                                        />
                                        <StatTile
                                            icon={HeartPulse}
                                            label="Overdue follow-ups"
                                            value={executive?.overdue_follow_ups ?? 0}
                                            alert
                                            href={slug ? `/${slug}/work` : null}
                                        />
                                    </div>
                                </section>

                                <section className={card}>
                                    <CardHeader
                                        title="Team workload"
                                        action={
                                            casesHref ? (
                                                <Link
                                                    href={casesHref}
                                                    className="flex size-8 items-center justify-center rounded-full bg-[#f1f2ee] text-[#12281c] hover:bg-[#e4ecd9] dark:bg-[#1c2820] dark:text-[#e9f0e5]"
                                                    aria-label="Open all cases"
                                                >
                                                    <Search className="size-4" />
                                                </Link>
                                            ) : null
                                        }
                                    />
                                    {(metrics.workload_by_coordinator ?? []).length === 0 ? (
                                        <p className={`rounded-2xl bg-[#f1f2ee] px-4 py-8 text-center text-sm dark:bg-[#1c2820] ${soft}`}>
                                            Coordinator workload shows here once cases are assigned.
                                        </p>
                                    ) : (
                                        <ul className="space-y-2">
                                            {(metrics.workload_by_coordinator ?? []).slice(0, 5).map((row) => (
                                                <li
                                                    key={row.coordinator}
                                                    className="flex items-center gap-3 rounded-2xl bg-[#f1f2ee] px-3 py-2.5 dark:bg-[#1c2820]"
                                                >
                                                    <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#cbd7b8] text-xs font-semibold text-[#26412f]">
                                                        {initials(row.coordinator) || <UserRound className="size-4" />}
                                                    </span>
                                                    <div className="min-w-0 flex-1">
                                                        <p className={`truncate text-sm font-semibold ${ink}`}>
                                                            {row.coordinator}
                                                        </p>
                                                        <p className={`text-[11px] ${soft}`}>Coordinator</p>
                                                    </div>
                                                    <span
                                                        className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${
                                                            row.open_cases > 8
                                                                ? 'bg-[#f6e2d8] text-[#b2492f]'
                                                                : 'bg-[#e2efd6] text-[#3f6a2a]'
                                                        }`}
                                                    >
                                                        {row.open_cases} open
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </section>

                                <section className={card}>
                                    <CardHeader
                                        title="Work today"
                                        count={metrics.recent_cases.length}
                                        action={
                                            casesHref ? (
                                                <Link
                                                    href={casesHref}
                                                    className={`inline-flex items-center gap-1 text-sm font-medium ${ink} hover:underline`}
                                                >
                                                    <Plus className="size-3.5" />
                                                    View all
                                                </Link>
                                            ) : null
                                        }
                                    />
                                    {metrics.recent_cases.length === 0 ? (
                                        <p className={`rounded-2xl bg-[#f1f2ee] px-4 py-8 text-center text-sm dark:bg-[#1c2820] ${soft}`}>
                                            New enquiries and case updates will show up here.
                                        </p>
                                    ) : (
                                        <ul className="divide-y divide-[#e6e9e0] dark:divide-[#26332a]">
                                            {metrics.recent_cases.slice(0, 5).map((item) => {
                                                const when = formatWhen(item.last_activity_at);

                                                return (
                                                    <li key={item.reference}>
                                                        <Link
                                                            href={slug ? `/${slug}/cases/${item.reference}` : '#'}
                                                            className="group flex items-center gap-3 py-3"
                                                        >
                                                            <div className="w-12 shrink-0">
                                                                <p className={`text-sm font-semibold tabular-nums ${ink}`}>
                                                                    {when.top}
                                                                </p>
                                                                {when.bottom ? (
                                                                    <p className={`text-[10px] ${soft}`}>{when.bottom}</p>
                                                                ) : null}
                                                            </div>
                                                            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#cbd7b8] text-xs font-semibold text-[#26412f]">
                                                                {initials(item.patient_name)}
                                                            </span>
                                                            <div className="min-w-0 flex-1">
                                                                <p className={`truncate text-sm font-semibold ${ink}`}>
                                                                    {item.patient_name}
                                                                </p>
                                                                <p className={`truncate text-[11px] ${soft}`}>
                                                                    {item.status_label}
                                                                </p>
                                                            </div>
                                                            <ChevronRight className="size-4 shrink-0 rounded-full bg-[#f1f2ee] p-0.5 text-[#6b7a70] transition group-hover:bg-[#e4ecd9] dark:bg-[#1c2820]" />
                                                        </Link>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    )}
                                </section>
                            </div>

                            {/* Row 2 */}
                            <div className="grid gap-5 xl:grid-cols-[0.8fr_1.7fr_1fr]">
                                <div className="grid gap-5">
                                    <section className={card}>
                                        <p className={`text-6xl font-medium tracking-tight tabular-nums ${ink}`}>
                                            {operational?.enquiry_to_proposal_conversion ?? 0}
                                            <span className="text-3xl">%</span>
                                        </p>
                                        <p className={`mt-1 text-sm font-medium ${ink}`}>
                                            Enquiry → proposal
                                        </p>
                                        <p className={`mb-4 text-xs ${soft}`}>
                                            Of all enquiries, how many got a proposal
                                        </p>
                                        <FunnelBars values={funnel} />
                                    </section>
                                    <section className={card}>
                                        <div className="flex items-center justify-between">
                                            <h2 className={`text-base font-semibold ${ink}`}>
                                                Proposal acceptance
                                            </h2>
                                            <CalendarCheck className={`size-4 ${soft}`} />
                                        </div>
                                        <div className={`mt-4 flex items-center justify-between text-xs ${soft}`}>
                                            <span>Accepted by patients</span>
                                            <span className={`font-semibold ${ink}`}>
                                                {operational?.proposal_to_acceptance_conversion ?? 0}%
                                            </span>
                                        </div>
                                        <div className="mt-2 h-2.5 overflow-hidden rounded-full bg-[#e4e8dd] dark:bg-[#26332a]">
                                            <div
                                                className="h-full rounded-full bg-[#6f8f4e]"
                                                style={{
                                                    width: `${Math.min(operational?.proposal_to_acceptance_conversion ?? 0, 100)}%`,
                                                }}
                                            />
                                        </div>
                                    </section>
                                </div>

                                <section className={`${card} grid gap-5 lg:grid-cols-[1fr_1fr]`}>
                                    <div className="flex flex-col">
                                        <h2 className={`text-xl font-semibold tracking-tight ${ink}`}>
                                            Patient journey analysis
                                        </h2>
                                        <p className={`mt-1 text-xs ${soft}`}>
                                            Where cases are waiting right now
                                        </p>
                                        <div className="mt-auto grid grid-cols-2 gap-3 pt-6">
                                            <Link
                                                href={casesHref ?? '#'}
                                                className={`${tile} p-4 transition hover:-translate-y-0.5`}
                                            >
                                                <FileText className="size-5 text-[#5f7d45] dark:text-[#a9c58a]" />
                                                <p className={`mt-3 text-sm font-medium ${ink}`}>
                                                    Documents to review
                                                </p>
                                                <p className={`mt-2 text-3xl font-semibold tabular-nums ${ink}`}>
                                                    {metrics.documents_needing_review}
                                                </p>
                                                <p className={`text-xs ${soft}`}>need a human check</p>
                                            </Link>
                                            <Link
                                                href={casesHref ?? '#'}
                                                className={`${tile} p-4 transition hover:-translate-y-0.5`}
                                            >
                                                <Hourglass className="size-5 text-[#5f7d45] dark:text-[#a9c58a]" />
                                                <p className={`mt-3 text-sm font-medium ${ink}`}>
                                                    Waiting on patients
                                                </p>
                                                <p className={`mt-2 text-3xl font-semibold tabular-nums ${ink}`}>
                                                    {executive?.awaiting_patient_response ?? 0}
                                                </p>
                                                <p className={`text-xs ${soft}`}>owe us a reply</p>
                                            </Link>
                                        </div>
                                    </div>
                                    <div className="rounded-[24px] bg-[#cbd7b8] p-4">
                                        <p className="mb-2 text-sm font-semibold text-[#26412f]">
                                            Case flow
                                        </p>
                                        <Donut segments={flow} />
                                    </div>
                                </section>

                                <section className="relative flex min-h-[320px] flex-col overflow-hidden rounded-[28px] bg-[#7b9a58] p-5 text-white md:p-6">
                                    <div aria-hidden className="absolute -top-16 -left-10 size-64 rounded-full bg-[#d6e5b4]/70 blur-3xl" />
                                    <div aria-hidden className="absolute -right-16 bottom-0 size-72 rounded-full bg-[#4f6e36]/80 blur-3xl" />
                                    <div className="relative flex items-center gap-2">
                                        <Sparkles className="size-4" />
                                        <h2 className="text-lg font-semibold">Shortcuts</h2>
                                    </div>
                                    <p className="relative mt-1 text-xs text-white/80">
                                        Jump straight to the work that needs you.
                                    </p>
                                    <div className="relative mt-auto space-y-3 pt-6">
                                        <div className="flex flex-wrap gap-2">
                                            {shortcuts.map((item) =>
                                                item.href ? (
                                                    <Link
                                                        key={item.label}
                                                        href={item.href}
                                                        className="rounded-full bg-white/20 px-3 py-1.5 text-[11px] font-medium backdrop-blur hover:bg-white/30"
                                                    >
                                                        {item.label}
                                                    </Link>
                                                ) : null,
                                            )}
                                        </div>
                                        <form
                                            onSubmit={goQuickFind}
                                            className="rounded-2xl bg-white/20 p-2 backdrop-blur"
                                        >
                                            <div className="flex items-center gap-2 rounded-xl bg-white px-3 py-2 text-[#12281c]">
                                                <input
                                                    value={quickFind}
                                                    onChange={(event) => setQuickFind(event.target.value)}
                                                    placeholder="Find a patient or case…"
                                                    aria-label="Find a patient or case"
                                                    className="h-8 flex-1 bg-transparent text-sm outline-none placeholder:text-[#8a978d]"
                                                />
                                                <button
                                                    type="submit"
                                                    aria-label="Search"
                                                    className="flex size-8 items-center justify-center rounded-full bg-[#12281c] text-white hover:bg-[#1c3a29]"
                                                >
                                                    <SendHorizontal className="size-4" />
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </section>
                            </div>
                        </>
                    )}
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
