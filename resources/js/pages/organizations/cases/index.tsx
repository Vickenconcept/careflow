import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowUpRight,
    Building2,
    ClipboardList,
    Copy,
    Download,
    Eye,
    FileText,
    Flame,
    Hourglass,
    FolderHeart,
    Languages,
    MapPin,
    PanelRight,
    RefreshCcw,
    RotateCcw,
    Save,
    Search,
    Sparkles,
    Stethoscope,
    UserRound,
    UserRoundCog,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
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
import PageTabs from '@/components/page-tabs';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

const UNASSIGNED = '__unassigned__';

type CaseRow = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    priority: string;
    patient_name: string;
    country: string | null;
    preferred_language: string;
    department: string | null;
    assigned_coordinator: string | null;
    assigned_doctor: string | null;
    assigned_coordinator_id?: number | null;
    assigned_doctor_id?: number | null;
    available_transitions?: Option[];
    documents_count?: number;
    last_activity_at: string | null;
    is_demo: boolean;
};

type MemberOption = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
};

type Option = { value: string; label: string };
type DepartmentOption = { id: number; name: string };

type Props = {
    team: { id: number; name: string; slug: string };
    cases: {
        data: CaseRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total?: number;
    };
    filters: {
        search: string | null;
        status: string | null;
        priority: string | null;
        department_id: number | null;
        assignment: string | null;
        sort: string;
    };
    filterOptions: {
        statuses: Option[];
        priorities: Option[];
        departments: DepartmentOption[];
        assignments: Option[];
        sorts: Option[];
    };
    assignableMembers?: {
        coordinators: MemberOption[];
        doctors: MemberOption[];
    };
    permissions?: {
        canAssignCases: boolean;
        canUpdateStatus: boolean;
        canExport?: boolean;
    };
    quickViewCounts?: Record<string, number>;
    savedViews?: SavedView[];
};

type SavedView = {
    id: number;
    name: string;
    filters: Record<string, string | number | null>;
};

const ALL = '__all__';

const PRIORITY_STYLES: Record<string, string> = {
    urgent: 'border-rose-200 bg-rose-50 text-rose-700',
    high: 'border-orange-200 bg-orange-50 text-orange-700',
    normal: 'border-sky-200 bg-sky-50 text-sky-700',
    low: 'border-slate-200 bg-slate-50 text-slate-600',
};

const STATUS_STYLES: Record<string, string> = {
    new_enquiry: 'border-blue-200 bg-blue-50 text-blue-700',
    awaiting_documents: 'border-amber-200 bg-amber-50 text-amber-700',
    documents_received: 'border-sky-200 bg-sky-50 text-sky-700',
    ai_processing: 'border-violet-200 bg-violet-50 text-violet-700',
    awaiting_coordinator_review: 'border-indigo-200 bg-indigo-50 text-indigo-700',
    awaiting_doctor_review: 'border-indigo-200 bg-indigo-50 text-indigo-700',
    additional_information_required:
        'border-amber-200 bg-amber-50 text-amber-700',
    treatment_proposal_preparation:
        'border-cyan-200 bg-cyan-50 text-cyan-700',
    proposal_sent: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    awaiting_patient_response: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    appointment_planning: 'border-teal-200 bg-teal-50 text-teal-700',
    travel_coordination: 'border-teal-200 bg-teal-50 text-teal-700',
    treatment_scheduled: 'border-teal-200 bg-teal-50 text-teal-700',
    treatment_completed: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    follow_up: 'border-blue-200 bg-blue-50 text-blue-700',
    closed: 'border-slate-200 bg-slate-100 text-slate-600',
    declined: 'border-rose-200 bg-rose-50 text-rose-700',
};

function formatActivity(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    const diffMs = Date.now() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);

    if (diffMins < 1) {
        return 'Just now';
    }

    if (diffMins < 60) {
        return `${diffMins}m ago`;
    }

    const diffHours = Math.floor(diffMins / 60);

    if (diffHours < 24) {
        return `${diffHours}h ago`;
    }

    const diffDays = Math.floor(diffHours / 24);

    if (diffDays < 7) {
        return `${diffDays}d ago`;
    }

    return date.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}

function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

export default function CasesIndex({
    team,
    cases,
    filters,
    filterOptions,
    assignableMembers = { coordinators: [], doctors: [] },
    permissions = { canAssignCases: false, canUpdateStatus: false },
    quickViewCounts = {},
    savedViews = [],
}: Props) {
    const [savingView, setSavingView] = useState(false);
    const [viewName, setViewName] = useState('');
    const [viewError, setViewError] = useState<string | null>(null);
    const [bulkCoordinator, setBulkCoordinator] = useState(UNASSIGNED);
    const [bulkDoctor, setBulkDoctor] = useState(UNASSIGNED);
    const [bulkProcessing, setBulkProcessing] = useState(false);
    const [bulkError, setBulkError] = useState<string | null>(null);

    const currentFilters = Object.fromEntries(
        Object.entries({
            search: filters.search,
            status: filters.status,
            priority: filters.priority,
            department_id: filters.department_id,
            assignment: filters.assignment,
            sort: filters.sort !== 'recent' ? filters.sort : null,
        }).filter(([, value]) => value !== null && value !== undefined && value !== ''),
    );
    const hasFilters = Object.keys(currentFilters).length > 0;

    const saveView = () => {
        setViewError(null);
        router.post(
            `/${team.slug}/case-views`,
            { name: viewName, filters: currentFilters },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSavingView(false);
                    setViewName('');
                },
                onError: (errors) =>
                    setViewError(errors.name ?? 'Could not save this view.'),
            },
        );
    };

    const applySavedView = (view: SavedView) => {
        router.get(`/${team.slug}/cases`, view.filters, {
            preserveState: false,
            replace: true,
        });
    };

    const removeSavedView = (view: SavedView) => {
        router.delete(`/${team.slug}/case-views/${view.id}`, {
            preserveScroll: true,
        });
    };

    const submitBulkAssignment = () => {
        setBulkError(null);
        const coordinatorId =
            bulkCoordinator === UNASSIGNED ? null : Number(bulkCoordinator);
        const doctorId = bulkDoctor === UNASSIGNED ? null : Number(bulkDoctor);

        if (coordinatorId === null && doctorId === null) {
            setBulkError('Choose a coordinator or a doctor.');

            return;
        }

        setBulkProcessing(true);
        router.post(
            `/${team.slug}/cases/bulk-assignment`,
            {
                case_ids: selected,
                coordinator_id: coordinatorId,
                doctor_id: doctorId,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelected([]);
                    setBulkCoordinator(UNASSIGNED);
                    setBulkDoctor(UNASSIGNED);
                },
                onError: (errors) =>
                    setBulkError(
                        errors.assignment ??
                            Object.values(errors)[0] ??
                            'Could not assign these cases.',
                    ),
                onFinish: () => setBulkProcessing(false),
            },
        );
    };

    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? ALL);
    const [priority, setPriority] = useState(filters.priority ?? ALL);
    const [departmentId, setDepartmentId] = useState(
        filters.department_id ? String(filters.department_id) : ALL,
    );
    const [assignment, setAssignment] = useState(filters.assignment ?? ALL);
    const [sort, setSort] = useState(filters.sort ?? 'recent');
    const [selected, setSelected] = useState<number[]>([]);
    const [manageOpen, setManageOpen] = useState(false);
    const [selectedCase, setSelectedCase] = useState<CaseRow | null>(null);

    const statusForm = useForm({
        status: '',
        note: '',
    });

    const assignmentForm = useForm({
        coordinator_id: '',
        doctor_id: '',
        clear_coordinator: false,
        clear_doctor: false,
    });

    const caseCount = cases.total ?? cases.data.length;
    const allSelected =
        cases.data.length > 0 &&
        cases.data.every((item) => selected.includes(item.id));

    const activeFilterChips = useMemo(() => {
        const chips: string[] = [];

        if (filters.search) {
            chips.push(`“${filters.search}”`);
        }

        if (filters.priority) {
            chips.push(
                filterOptions.priorities.find(
                    (item) => item.value === filters.priority,
                )?.label ?? filters.priority,
            );
        }

        if (filters.status) {
            chips.push(
                filterOptions.statuses.find(
                    (item) => item.value === filters.status,
                )?.label ?? filters.status,
            );
        }

        if (filters.department_id) {
            chips.push(
                filterOptions.departments.find(
                    (item) => item.id === filters.department_id,
                )?.name ?? 'Department',
            );
        }

        if (filters.assignment) {
            chips.push(
                filterOptions.assignments.find(
                    (item) => item.value === filters.assignment,
                )?.label ?? filters.assignment,
            );
        }

        return chips;
    }, [filters, filterOptions]);

    const openManageCase = (item: CaseRow) => {
        const transitions = item.available_transitions ?? [];

        setSelectedCase(item);
        statusForm.clearErrors();
        statusForm.setData({
            status: transitions[0]?.value ?? '',
            note: '',
        });
        assignmentForm.clearErrors();
        assignmentForm.setData({
            coordinator_id: item.assigned_coordinator_id
                ? String(item.assigned_coordinator_id)
                : '',
            doctor_id: item.assigned_doctor_id
                ? String(item.assigned_doctor_id)
                : '',
            clear_coordinator: false,
            clear_doctor: false,
        });
        setManageOpen(true);
    };

    useEffect(() => {
        if (!manageOpen || !selectedCase) {
            return;
        }

        const updated = cases.data.find((item) => item.id === selectedCase.id);

        if (updated) {
            setSelectedCase(updated);
        }
    }, [cases.data, manageOpen, selectedCase?.id]);

    const applyFilters = (overrides: Partial<{
        search: string;
        status: string;
        priority: string;
        departmentId: string;
        assignment: string;
        sort: string;
    }> = {}) => {
        const nextSearch = overrides.search ?? search;
        const nextStatus = overrides.status ?? status;
        const nextPriority = overrides.priority ?? priority;
        const nextDepartmentId = overrides.departmentId ?? departmentId;
        const nextAssignment = overrides.assignment ?? assignment;
        const nextSort = overrides.sort ?? sort;

        router.get(
            `/${team.slug}/cases`,
            {
                search: nextSearch || undefined,
                status: nextStatus === ALL ? undefined : nextStatus,
                priority: nextPriority === ALL ? undefined : nextPriority,
                department_id:
                    nextDepartmentId === ALL ? undefined : nextDepartmentId,
                assignment:
                    nextAssignment === ALL ? undefined : nextAssignment,
                sort: nextSort || undefined,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    useEffect(() => {
        const handle = window.setTimeout(() => {
            if (search === (filters.search ?? '')) {
                return;
            }

            applyFilters({ search });
        }, 350);

        return () => window.clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const quickViewIcons: Record<string, typeof ClipboardList> = {
        all: ClipboardList,
        mine: UserRound,
        unassigned: UserRoundCog,
        awaiting_documents: FileText,
        awaiting_doctor_review: Stethoscope,
        awaiting_patient_response: Hourglass,
    };

    const quickViews: Array<{
        id: string;
        label: string;
        status: string;
        assignment: string;
    }> = [
        { id: 'all', label: 'All cases', status: ALL, assignment: ALL },
        { id: 'mine', label: 'My cases', status: ALL, assignment: 'mine' },
        {
            id: 'unassigned',
            label: 'Unassigned',
            status: ALL,
            assignment: 'unassigned',
        },
        {
            id: 'awaiting_documents',
            label: 'Awaiting documents',
            status: 'awaiting_documents',
            assignment: ALL,
        },
        {
            id: 'awaiting_doctor_review',
            label: 'Awaiting doctor review',
            status: 'awaiting_doctor_review',
            assignment: ALL,
        },
        {
            id: 'awaiting_patient_response',
            label: 'Awaiting patient',
            status: 'awaiting_patient_response',
            assignment: ALL,
        },
    ];

    const activeQuickView =
        quickViews.find(
            (view) =>
                (view.status === ALL ? null : view.status) ===
                    (filters.status ?? null) &&
                (view.assignment === ALL ? null : view.assignment) ===
                    (filters.assignment ?? null),
        )?.id ?? null;

    const selectQuickView = (view: (typeof quickViews)[number]) => {
        setStatus(view.status);
        setAssignment(view.assignment);
        applyFilters({ status: view.status, assignment: view.assignment });
    };

    const toggleAll = (checked: boolean) => {
        setSelected(checked ? cases.data.map((item) => item.id) : []);
    };

    const toggleRow = (id: number, checked: boolean) => {
        setSelected((current) =>
            checked
                ? [...current, id]
                : current.filter((value) => value !== id),
        );
    };

    const copyReference = async (reference: string) => {
        try {
            await navigator.clipboard.writeText(reference);
        } catch {
            // Clipboard may be unavailable in some browsers.
        }
    };

    return (
        <TooltipProvider>
            <Head title="Cases" />
            <div className="space-y-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2">
                        <h1 className="text-2xl font-semibold tracking-tight text-slate-900">
                            Cases
                        </h1>
                        <div className="flex flex-wrap items-center gap-2 text-sm text-slate-500">
                            <span className="font-medium text-slate-700">
                                {caseCount}{' '}
                                {caseCount === 1 ? 'case' : 'cases'}
                            </span>
                            {activeFilterChips.length > 0 ? (
                                <span className="inline-flex items-center rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600">
                                    {activeFilterChips.join(' · ')}
                                </span>
                            ) : null}
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="h-9 rounded-xl"
                            onClick={() =>
                                router.get(
                                    `/${team.slug}/cases`,
                                    {},
                                    {
                                        preserveState: false,
                                        replace: true,
                                    },
                                )
                            }
                        >
                            <RotateCcw className="size-4" />
                            Reset
                        </Button>
                        {permissions.canExport ? (
                            <Button
                                asChild
                                variant="outline"
                                className="h-9 rounded-xl"
                            >
                                <a
                                    href={`/${team.slug}/cases/export?${new URLSearchParams(
                                        Object.entries({
                                            search: filters.search,
                                            status: filters.status,
                                            priority: filters.priority,
                                            department_id: filters.department_id,
                                            assignment: filters.assignment,
                                            sort: filters.sort,
                                        }).flatMap(([key, value]) =>
                                            value === null ||
                                            value === undefined ||
                                            value === ''
                                                ? []
                                                : [[key, String(value)]],
                                        ),
                                    ).toString()}`}
                                >
                                    <Download className="size-4" />
                                    Export CSV
                                </a>
                            </Button>
                        ) : null}
                        <Button
                            asChild
                            className="h-9 rounded-xl bg-brand text-white hover:bg-brand/90"
                        >
                            <Link
                                href={`/p/${team.slug}/intake`}
                                target="_blank"
                            >
                                <Sparkles className="size-4" />
                                Public intake
                            </Link>
                        </Button>
                    </div>
                </div>

                <PageTabs
                    label="Case views"
                    active={activeQuickView ?? ''}
                    onSelect={(id) => {
                        const view = quickViews.find((item) => item.id === id);

                        if (view) {
                            selectQuickView(view);
                        }
                    }}
                    tabs={quickViews.map((view) => ({
                        id: view.id,
                        label: view.label,
                        icon: quickViewIcons[view.id],
                        count: quickViewCounts[view.id],
                    }))}
                />

                {savedViews.length > 0 || hasFilters ? (
                    <div className="flex flex-wrap items-center gap-2">
                        {savedViews.length > 0 ? (
                            <span className="text-xs font-medium text-slate-500">
                                Saved views
                            </span>
                        ) : null}
                        {savedViews.map((view) => (
                            <span
                                key={view.id}
                                className="inline-flex items-center overflow-hidden rounded-full border border-slate-200 bg-white text-xs font-medium text-slate-700"
                            >
                                <button
                                    type="button"
                                    onClick={() => applySavedView(view)}
                                    className="px-3 py-1.5 hover:bg-brand/5 hover:text-brand"
                                >
                                    {view.name}
                                </button>
                                <button
                                    type="button"
                                    aria-label={`Remove saved view ${view.name}`}
                                    onClick={() => removeSavedView(view)}
                                    className="border-l border-slate-200 px-2 py-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                                >
                                    <X className="size-3" />
                                </button>
                            </span>
                        ))}
                        {hasFilters && !savingView ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className="h-8 rounded-full"
                                onClick={() => setSavingView(true)}
                            >
                                <Save className="size-3.5" />
                                Save current filters
                            </Button>
                        ) : null}
                        {savingView ? (
                            <form
                                className="flex flex-wrap items-center gap-2"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    saveView();
                                }}
                            >
                                <Input
                                    autoFocus
                                    value={viewName}
                                    onChange={(event) =>
                                        setViewName(event.target.value)
                                    }
                                    placeholder="Name this view"
                                    maxLength={60}
                                    aria-label="Saved view name"
                                    className="h-8 w-48 rounded-full"
                                />
                                <Button
                                    type="submit"
                                    size="sm"
                                    className="h-8 rounded-full bg-brand text-white hover:bg-brand/90"
                                    disabled={viewName.trim() === ''}
                                >
                                    Save
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    className="h-8 rounded-full"
                                    onClick={() => {
                                        setSavingView(false);
                                        setViewError(null);
                                    }}
                                >
                                    Cancel
                                </Button>
                                {viewError ? (
                                    <span className="text-xs text-rose-600">
                                        {viewError}
                                    </span>
                                ) : null}
                            </form>
                        ) : null}
                    </div>
                ) : null}

                <div className="flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200/80 bg-white p-2 shadow-[0_10px_30px_-24px_rgba(15,23,42,0.45)]">
                    <div className="relative min-w-[220px] flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search reference, name, email"
                            className="h-10 border-0 bg-slate-50 pl-9 shadow-none focus-visible:ring-1"
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-1 rounded-xl bg-slate-50 p-1">
                        <PriorityChip
                            active={priority === ALL}
                            label="All"
                            onClick={() => {
                                setPriority(ALL);
                                applyFilters({ priority: ALL });
                            }}
                        />
                        {filterOptions.priorities.map((option) => (
                            <PriorityChip
                                key={option.value}
                                active={priority === option.value}
                                label={option.label}
                                tone={option.value}
                                onClick={() => {
                                    setPriority(option.value);
                                    applyFilters({ priority: option.value });
                                }}
                            />
                        ))}
                    </div>

                    <CompactSelect
                        value={status}
                        onChange={(value) => {
                            setStatus(value);
                            applyFilters({ status: value });
                        }}
                        placeholder="All statuses"
                        options={[
                            { value: ALL, label: 'All statuses' },
                            ...filterOptions.statuses,
                        ]}
                    />

                    <CompactSelect
                        value={departmentId}
                        onChange={(value) => {
                            setDepartmentId(value);
                            applyFilters({ departmentId: value });
                        }}
                        placeholder="All departments"
                        options={[
                            { value: ALL, label: 'All departments' },
                            ...filterOptions.departments.map((department) => ({
                                value: String(department.id),
                                label: department.name,
                            })),
                        ]}
                    />

                    <CompactSelect
                        value={assignment}
                        onChange={(value) => {
                            setAssignment(value);
                            applyFilters({ assignment: value });
                        }}
                        placeholder="Assignment"
                        options={[
                            { value: ALL, label: 'All assignments' },
                            ...filterOptions.assignments,
                        ]}
                    />

                    <CompactSelect
                        value={sort}
                        onChange={(value) => {
                            setSort(value);
                            applyFilters({ sort: value });
                        }}
                        placeholder="Sort"
                        options={filterOptions.sorts}
                    />
                </div>

                {cases.data.length === 0 ? (
                    <EmptyState
                        icon={FolderHeart}
                        title="No cases match these filters"
                        description="Try clearing filters, or open the public intake form to create a new enquiry."
                        action={
                            <Button
                                asChild
                                className="rounded-xl bg-brand text-white hover:bg-brand/90"
                            >
                                <Link
                                    href={`/p/${team.slug}/intake`}
                                    target="_blank"
                                >
                                    Open public intake
                                </Link>
                            </Button>
                        }
                    />
                ) : (
                    <DataTableCard>
                        <DataTable minWidthClassName="min-w-[960px]">
                            <DataTableHeader>
                                <DataTableHead className="w-12">
                                    <Checkbox
                                        checked={allSelected}
                                        onCheckedChange={(checked) =>
                                            toggleAll(checked === true)
                                        }
                                        aria-label="Select all cases"
                                    />
                                </DataTableHead>
                                <DataTableHead>Patient</DataTableHead>
                                <DataTableHead>Priority</DataTableHead>
                                <DataTableHead>Status</DataTableHead>
                                <DataTableHead>Department</DataTableHead>
                                <DataTableHead>Team</DataTableHead>
                                <DataTableHead>Activity</DataTableHead>
                                <DataTableHead className="text-right">
                                    Actions
                                </DataTableHead>
                            </DataTableHeader>
                            <DataTableBody>
                                    {cases.data.map((item) => {
                                        const priorityLabel =
                                            filterOptions.priorities.find(
                                                (option) =>
                                                    option.value ===
                                                    item.priority,
                                            )?.label ?? item.priority;
                                        const caseHref = `/${team.slug}/cases/${item.reference}`;
                                        const checked = selected.includes(
                                            item.id,
                                        );

                                        return (
                                            <DataTableRow
                                                key={item.id}
                                                onClick={(event) => {
                                                    if (
                                                        (
                                                            event.target as HTMLElement
                                                        ).closest(
                                                            'a,button,input,label,[role=checkbox]',
                                                        )
                                                    ) {
                                                        return;
                                                    }

                                                    router.visit(caseHref);
                                                }}
                                                className={
                                                    checked
                                                        ? 'bg-brand/5'
                                                        : undefined
                                                }
                                            >
                                                <DataTableCell>
                                                    <Checkbox
                                                        checked={checked}
                                                        onCheckedChange={(
                                                            value,
                                                        ) =>
                                                            toggleRow(
                                                                item.id,
                                                                value === true,
                                                            )
                                                        }
                                                        aria-label={`Select ${item.reference}`}
                                                    />
                                                </DataTableCell>
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
                                                                    href={
                                                                        caseHref
                                                                    }
                                                                    className="truncate text-left font-semibold text-slate-900 hover:text-brand"
                                                                >
                                                                    {
                                                                        item.patient_name
                                                                    }
                                                                </Link>
                                                                {item.is_demo ? (
                                                                    <Badge
                                                                        variant="secondary"
                                                                        className="rounded-full"
                                                                    >
                                                                        Demo
                                                                    </Badge>
                                                                ) : null}
                                                            </div>
                                                            <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
                                                                <span className="font-mono text-[11px] text-brand">
                                                                    {
                                                                        item.reference
                                                                    }
                                                                </span>
                                                                {(item.country ||
                                                                    item.preferred_language) && (
                                                                    <span className="inline-flex items-center gap-1">
                                                                        <MapPin className="size-3" />
                                                                        {[
                                                                            item.country,
                                                                            item.preferred_language,
                                                                        ]
                                                                            .filter(
                                                                                Boolean,
                                                                            )
                                                                            .join(
                                                                                ' · ',
                                                                            )}
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </DataTableCell>
                                                <DataTableCell>
                                                    <span
                                                        className={cn(
                                                            'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold',
                                                            PRIORITY_STYLES[
                                                                item.priority
                                                            ] ??
                                                                PRIORITY_STYLES.normal,
                                                        )}
                                                    >
                                                        {(item.priority ===
                                                            'urgent' ||
                                                            item.priority ===
                                                                'high') && (
                                                            <Flame className="size-3.5" />
                                                        )}
                                                        {priorityLabel}
                                                    </span>
                                                </DataTableCell>
                                                <DataTableCell>
                                                    <span
                                                        className={cn(
                                                            'inline-flex max-w-[180px] truncate rounded-full border px-2.5 py-1 text-xs font-medium',
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
                                                <DataTableCell>
                                                    <div className="space-y-0.5">
                                                        <div className="text-slate-800">
                                                            {item.assigned_coordinator ?? (
                                                                <span className="text-slate-400">
                                                                    No
                                                                    coordinator
                                                                </span>
                                                            )}
                                                        </div>
                                                        <div className="text-xs text-slate-500">
                                                            {item.assigned_doctor ??
                                                                'No doctor'}
                                                        </div>
                                                    </div>
                                                </DataTableCell>
                                                <DataTableCell className="whitespace-nowrap text-slate-500">
                                                    {formatActivity(
                                                        item.last_activity_at,
                                                    )}
                                                </DataTableCell>
                                                <DataTableCell>
                                                    <div className="flex items-center justify-end gap-1.5">
                                                        <ActionButton
                                                            label="Open case"
                                                            onClick={() =>
                                                                router.visit(
                                                                    caseHref,
                                                                )
                                                            }
                                                        >
                                                            <Eye className="size-4" />
                                                        </ActionButton>
                                                        <ActionButton
                                                            label="Copy reference"
                                                            onClick={() =>
                                                                copyReference(
                                                                    item.reference,
                                                                )
                                                            }
                                                        >
                                                            <Copy className="size-4" />
                                                        </ActionButton>
                                                        <ActionButton
                                                            label="Open in new tab"
                                                            href={caseHref}
                                                        >
                                                            <ArrowUpRight className="size-4" />
                                                        </ActionButton>
                                                        <ActionButton
                                                            label="Manage"
                                                            onClick={() =>
                                                                openManageCase(
                                                                    item,
                                                                )
                                                            }
                                                        >
                                                            <PanelRight className="size-4" />
                                                        </ActionButton>
                                                    </div>
                                                </DataTableCell>
                                            </DataTableRow>
                                        );
                                    })}
                            </DataTableBody>
                        </DataTable>

                        {cases.links.length > 3 ? (
                            <div className="flex flex-wrap items-center justify-end gap-1 border-t border-slate-100 px-4 py-3">
                                {cases.links.map((link, index) => (
                                    <Button
                                        key={`${link.label}-${index}`}
                                        type="button"
                                        size="sm"
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                        className={cn(
                                            'h-8 min-w-8 rounded-lg px-2',
                                            link.active &&
                                                'bg-brand text-white hover:bg-brand/90',
                                        )}
                                        disabled={!link.url}
                                        onClick={() => {
                                            if (link.url) {
                                                router.get(
                                                    link.url,
                                                    {},
                                                    {
                                                        preserveState: true,
                                                    },
                                                );
                                            }
                                        }}
                                    >
                                        {link.label
                                            .replaceAll('&laquo;', '«')
                                            .replaceAll('&raquo;', '»')
                                            .replaceAll('&amp;', '&')}
                                    </Button>
                                ))}
                            </div>
                        ) : null}
                    </DataTableCard>
                )}

                {selected.length > 0 ? (
                    <div className="fixed inset-x-3 bottom-4 z-20 mx-auto flex w-fit max-w-full flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm shadow-lg">
                        <span className="font-medium text-slate-800">
                            {selected.length} selected
                        </span>
                        {permissions.canAssignCases ? (
                            <>
                                <Select
                                    value={bulkCoordinator}
                                    onValueChange={setBulkCoordinator}
                                >
                                    <SelectTrigger
                                        aria-label="Bulk coordinator"
                                        className="h-8 w-44 rounded-full"
                                    >
                                        <SelectValue placeholder="Coordinator" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={UNASSIGNED}>
                                            Coordinator: no change
                                        </SelectItem>
                                        {assignableMembers.coordinators.map(
                                            (member) => (
                                                <SelectItem
                                                    key={member.id}
                                                    value={String(member.id)}
                                                >
                                                    {member.name}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <Select
                                    value={bulkDoctor}
                                    onValueChange={setBulkDoctor}
                                >
                                    <SelectTrigger
                                        aria-label="Bulk doctor"
                                        className="h-8 w-44 rounded-full"
                                    >
                                        <SelectValue placeholder="Doctor" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={UNASSIGNED}>
                                            Doctor: no change
                                        </SelectItem>
                                        {assignableMembers.doctors.map(
                                            (member) => (
                                                <SelectItem
                                                    key={member.id}
                                                    value={String(member.id)}
                                                >
                                                    {member.name}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <Button
                                    type="button"
                                    size="sm"
                                    className="rounded-full bg-brand text-white hover:bg-brand/90"
                                    disabled={bulkProcessing}
                                    onClick={submitBulkAssignment}
                                >
                                    <UserRoundCog className="size-4" />
                                    Assign
                                </Button>
                                {bulkError ? (
                                    <span className="text-xs text-rose-600">
                                        {bulkError}
                                    </span>
                                ) : null}
                            </>
                        ) : null}
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="rounded-full"
                            onClick={() => setSelected([])}
                        >
                            <X className="size-4" />
                            Clear
                        </Button>
                    </div>
                ) : null}
            </div>

            <Sheet open={manageOpen} onOpenChange={setManageOpen}>
                <SheetContent className="flex w-full flex-col gap-0 border-l border-slate-200/80 bg-[#f4f7fb] p-0 sm:max-w-md">
                    {selectedCase ? (
                        <>
                            <SheetHeader className="space-y-4 border-b border-slate-200/80 bg-white px-5 py-5 text-left">
                                <div className="flex items-start gap-3 pe-8">
                                    <div className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand/10 text-sm font-bold text-brand">
                                        {selectedCase.patient_name
                                            .split(' ')
                                            .filter(Boolean)
                                            .slice(0, 2)
                                            .map((part) =>
                                                part[0]?.toUpperCase(),
                                            )
                                            .join('') || 'P'}
                                    </div>
                                    <div className="min-w-0 flex-1 space-y-1">
                                        <SheetTitle className="truncate text-lg font-semibold text-slate-900">
                                            {selectedCase.patient_name}
                                        </SheetTitle>
                                        <SheetDescription asChild>
                                            <p className="font-mono text-xs font-medium text-brand">
                                                {selectedCase.reference}
                                            </p>
                                        </SheetDescription>
                                    </div>
                                </div>

                                <div className="flex flex-wrap items-center gap-2">
                                    <span
                                        className={cn(
                                            'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium',
                                            STATUS_STYLES[
                                                selectedCase.status
                                            ] ??
                                                'border-slate-200 bg-slate-50 text-slate-600',
                                        )}
                                    >
                                        <ClipboardList className="size-3.5" />
                                        {selectedCase.status_label}
                                    </span>
                                    <span className="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-600">
                                        <FileText className="size-3.5" />
                                        {selectedCase.documents_count ?? 0}{' '}
                                        {(selectedCase.documents_count ??
                                            0) === 1
                                            ? 'document'
                                            : 'documents'}
                                    </span>
                                    {selectedCase.department ? (
                                        <span className="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-600">
                                            <Building2 className="size-3.5" />
                                            {selectedCase.department}
                                        </span>
                                    ) : null}
                                    {selectedCase.preferred_language ? (
                                        <span className="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium uppercase text-slate-600">
                                            <Languages className="size-3.5" />
                                            {selectedCase.preferred_language}
                                        </span>
                                    ) : null}
                                </div>

                                <Button
                                    asChild
                                    className="h-10 w-full rounded-xl bg-brand text-white hover:bg-brand/90"
                                >
                                    <Link
                                        href={`/${team.slug}/cases/${selectedCase.reference}`}
                                    >
                                        <Eye className="size-4" />
                                        Open full case
                                        <ArrowUpRight className="size-4 opacity-80" />
                                    </Link>
                                </Button>
                            </SheetHeader>

                            <div className="flex-1 space-y-4 overflow-y-auto px-5 py-5">
                                {permissions.canUpdateStatus ? (
                                    <form
                                        className="space-y-4 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-[0_10px_30px_-24px_rgba(15,23,42,0.35)]"
                                        onSubmit={(event) => {
                                            event.preventDefault();
                                            statusForm.patch(
                                                `/${team.slug}/cases/${selectedCase.reference}/status`,
                                                { preserveScroll: true },
                                            );
                                        }}
                                    >
                                        <div className="flex items-center gap-2.5">
                                            <span className="flex size-8 items-center justify-center rounded-xl bg-sky-50 text-sky-600">
                                                <RefreshCcw className="size-4" />
                                            </span>
                                            <div>
                                                <h3 className="text-sm font-semibold text-slate-900">
                                                    Update status
                                                </h3>
                                                <p className="text-xs text-slate-500">
                                                    Move this case to the next
                                                    workflow step
                                                </p>
                                            </div>
                                        </div>
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor="manage-status"
                                                className="inline-flex items-center gap-1.5"
                                            >
                                                <ClipboardList className="size-3.5 text-slate-400" />
                                                Next status
                                            </Label>
                                            <Select
                                                value={
                                                    statusForm.data.status ||
                                                    undefined
                                                }
                                                onValueChange={(value) =>
                                                    statusForm.setData(
                                                        'status',
                                                        value,
                                                    )
                                                }
                                                disabled={
                                                    (
                                                        selectedCase.available_transitions ??
                                                        []
                                                    ).length === 0
                                                }
                                            >
                                                <SelectTrigger
                                                    id="manage-status"
                                                    className="w-full rounded-xl bg-white"
                                                >
                                                    <SelectValue placeholder="Select status" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {(
                                                        selectedCase.available_transitions ??
                                                        []
                                                    ).map((option) => (
                                                        <SelectItem
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={
                                                    statusForm.errors.status
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor="manage-status-note"
                                                className="inline-flex items-center gap-1.5"
                                            >
                                                <FileText className="size-3.5 text-slate-400" />
                                                Note
                                                <span className="font-normal text-slate-400">
                                                    optional
                                                </span>
                                            </Label>
                                            <Input
                                                id="manage-status-note"
                                                value={statusForm.data.note}
                                                onChange={(event) =>
                                                    statusForm.setData(
                                                        'note',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Add a short note for the timeline"
                                                className="rounded-xl bg-white"
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            className="h-10 w-full rounded-xl bg-brand text-white hover:bg-brand/90"
                                            disabled={
                                                statusForm.processing ||
                                                (
                                                    selectedCase.available_transitions ??
                                                    []
                                                ).length === 0
                                            }
                                        >
                                            <RefreshCcw className="size-4" />
                                            Update status
                                        </Button>
                                    </form>
                                ) : null}

                                {permissions.canAssignCases ? (
                                    <form
                                        className="space-y-4 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-[0_10px_30px_-24px_rgba(15,23,42,0.35)]"
                                        onSubmit={(event) => {
                                            event.preventDefault();
                                            assignmentForm.transform(
                                                (data) => ({
                                                    coordinator_id:
                                                        data.coordinator_id
                                                            ? Number(
                                                                  data.coordinator_id,
                                                              )
                                                            : null,
                                                    doctor_id: data.doctor_id
                                                        ? Number(
                                                              data.doctor_id,
                                                          )
                                                        : null,
                                                    clear_coordinator:
                                                        !data.coordinator_id,
                                                    clear_doctor:
                                                        !data.doctor_id,
                                                }),
                                            );
                                            assignmentForm.patch(
                                                `/${team.slug}/cases/${selectedCase.reference}/assignment`,
                                                {
                                                    preserveScroll: true,
                                                },
                                            );
                                        }}
                                    >
                                        <div className="flex items-center gap-2.5">
                                            <span className="flex size-8 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                                                <UserRoundCog className="size-4" />
                                            </span>
                                            <div>
                                                <h3 className="text-sm font-semibold text-slate-900">
                                                    Assignment
                                                </h3>
                                                <p className="text-xs text-slate-500">
                                                    Route the case to the right
                                                    care team
                                                </p>
                                            </div>
                                        </div>
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor="manage-coordinator"
                                                className="inline-flex items-center gap-1.5"
                                            >
                                                <UserRound className="size-3.5 text-slate-400" />
                                                Coordinator
                                            </Label>
                                            <Select
                                                value={
                                                    assignmentForm.data
                                                        .coordinator_id ||
                                                    UNASSIGNED
                                                }
                                                onValueChange={(value) =>
                                                    assignmentForm.setData(
                                                        'coordinator_id',
                                                        value === UNASSIGNED
                                                            ? ''
                                                            : value,
                                                    )
                                                }
                                            >
                                                <SelectTrigger
                                                    id="manage-coordinator"
                                                    className="w-full rounded-xl bg-white"
                                                >
                                                    <SelectValue placeholder="Unassigned" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        value={UNASSIGNED}
                                                    >
                                                        Unassigned
                                                    </SelectItem>
                                                    {assignableMembers.coordinators.map(
                                                        (member) => (
                                                            <SelectItem
                                                                key={member.id}
                                                                value={String(
                                                                    member.id,
                                                                )}
                                                            >
                                                                {member.name} (
                                                                {
                                                                    member.role_label
                                                                }
                                                                )
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor="manage-doctor"
                                                className="inline-flex items-center gap-1.5"
                                            >
                                                <Stethoscope className="size-3.5 text-slate-400" />
                                                Doctor
                                            </Label>
                                            <Select
                                                value={
                                                    assignmentForm.data
                                                        .doctor_id || UNASSIGNED
                                                }
                                                onValueChange={(value) =>
                                                    assignmentForm.setData(
                                                        'doctor_id',
                                                        value === UNASSIGNED
                                                            ? ''
                                                            : value,
                                                    )
                                                }
                                            >
                                                <SelectTrigger
                                                    id="manage-doctor"
                                                    className="w-full rounded-xl bg-white"
                                                >
                                                    <SelectValue placeholder="Unassigned" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        value={UNASSIGNED}
                                                    >
                                                        Unassigned
                                                    </SelectItem>
                                                    {assignableMembers.doctors.map(
                                                        (member) => (
                                                            <SelectItem
                                                                key={member.id}
                                                                value={String(
                                                                    member.id,
                                                                )}
                                                            >
                                                                {member.name} (
                                                                {
                                                                    member.role_label
                                                                }
                                                                )
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <InputError
                                            message={
                                                assignmentForm.errors
                                                    .coordinator_id ??
                                                assignmentForm.errors.doctor_id
                                            }
                                        />
                                        <Button
                                            type="submit"
                                            className="h-10 w-full rounded-xl bg-brand text-white hover:bg-brand/90"
                                            disabled={assignmentForm.processing}
                                        >
                                            <Save className="size-4" />
                                            Save assignment
                                        </Button>
                                    </form>
                                ) : null}
                            </div>
                        </>
                    ) : null}
                </SheetContent>
            </Sheet>
        </TooltipProvider>
    );
}

function PriorityChip({
    active,
    label,
    tone,
    onClick,
}: {
    active: boolean;
    label: string;
    tone?: string;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'rounded-lg px-2.5 py-1.5 text-xs font-semibold transition',
                active
                    ? 'bg-white text-brand shadow-sm ring-1 ring-brand/20'
                    : 'text-slate-500 hover:bg-white/70 hover:text-slate-700',
                active && tone === 'urgent' && 'text-rose-700 ring-rose-200',
                active && tone === 'high' && 'text-orange-700 ring-orange-200',
            )}
        >
            {label}
        </button>
    );
}

function CompactSelect({
    value,
    onChange,
    options,
    placeholder,
}: {
    value: string;
    onChange: (value: string) => void;
    options: Option[];
    placeholder: string;
}) {
    return (
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger className="h-10 w-auto min-w-[140px] rounded-xl border-slate-200 bg-white">
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function ActionButton({
    label,
    children,
    onClick,
    href,
}: {
    label: string;
    children: ReactNode;
    onClick?: () => void;
    href?: string;
}) {
    const className =
        'inline-flex size-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:border-brand/30 hover:bg-brand/5 hover:text-brand';

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                {href ? (
                    <Link
                        href={href}
                        target="_blank"
                        className={className}
                        aria-label={label}
                    >
                        {children}
                    </Link>
                ) : (
                    <button
                        type="button"
                        className={className}
                        aria-label={label}
                        onClick={onClick}
                    >
                        {children}
                    </button>
                )}
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}

CasesIndex.layout = (props: { team: { slug: string } }) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard(props.team.slug) },
        {
            title: 'Cases',
            href: `/${props.team.slug}/cases`,
        },
    ],
});
