import { Head, Link } from '@inertiajs/react';
import {
    CalendarClock,
    CheckCircle2,
    ClipboardCheck,
    MessageSquareText,
    Repeat,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { cn } from '@/lib/utils';

type WorkItem = {
    id: string;
    title: string;
    status_label: string;
    priority?: string;
    due_at: string | null;
    overdue: boolean;
    case: { reference: string; patient_name: string | null; url: string };
    tab: string;
};

type Props = {
    team: { id: number; name: string; slug: string };
    sections: {
        tasks: WorkItem[];
        follow_ups: WorkItem[];
        appointments: WorkItem[];
        messages: WorkItem[];
    };
    canApproveMessages: boolean;
};

function formatDue(value: string | null): string {
    if (!value) {
        return 'No due date';
    }

    return new Date(value).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function Section({
    title,
    description,
    icon: Icon,
    items,
    emptyText,
    dueLabel,
}: {
    title: string;
    description: string;
    icon: LucideIcon;
    items: WorkItem[];
    emptyText: string;
    dueLabel: string;
}) {
    return (
        <section className="overflow-hidden rounded-lg border border-border bg-card">
            <div className="flex items-center gap-2.5 border-b border-border px-5 py-4">
                <span className="flex size-8 items-center justify-center rounded-lg bg-brand/10 text-brand">
                    <Icon className="size-4" aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1">
                    <h2 className="text-sm font-semibold">
                        {title}
                        <span className="ms-2 rounded-full bg-muted px-2 py-0.5 text-xs font-semibold text-muted-foreground">
                            {items.length}
                        </span>
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        {description}
                    </p>
                </div>
            </div>
            {items.length === 0 ? (
                <p className="flex items-center gap-2 px-5 py-6 text-sm text-muted-foreground">
                    <CheckCircle2
                        className="size-4 text-emerald-600"
                        aria-hidden="true"
                    />
                    {emptyText}
                </p>
            ) : (
                <ul className="divide-y divide-border">
                    {items.map((item) => (
                        <li key={item.id}>
                            <Link
                                href={`${item.case.url}?tab=${item.tab}`}
                                className="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm hover:bg-muted/50"
                            >
                                <div className="min-w-0">
                                    <p className="truncate font-medium">
                                        {item.title}
                                    </p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        <span className="font-mono text-brand">
                                            {item.case.reference}
                                        </span>
                                        {item.case.patient_name
                                            ? ` · ${item.case.patient_name}`
                                            : ''}
                                        {' · '}
                                        {item.status_label}
                                    </p>
                                </div>
                                <span
                                    className={cn(
                                        'shrink-0 text-xs',
                                        item.overdue
                                            ? 'font-semibold text-red-600'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {item.overdue ? 'Overdue · ' : ''}
                                    {dueLabel}
                                    {formatDue(item.due_at)}
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

export default function Work({ sections, canApproveMessages }: Props) {
    const total =
        sections.tasks.length +
        sections.follow_ups.length +
        sections.appointments.length +
        sections.messages.length;
    const overdue = [...sections.tasks, ...sections.follow_ups].filter(
        (item) => item.overdue,
    ).length;

    return (
        <>
            <Head title="My work" />
            <div className="space-y-5 p-4 md:p-6">
                <header className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        My work
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {total === 0
                            ? 'Nothing needs your attention right now.'
                            : `${total} ${total === 1 ? 'item needs' : 'items need'} your attention${overdue > 0 ? `, ${overdue} overdue` : ''}.`}
                    </p>
                </header>

                {total === 0 ? (
                    <EmptyState
                        icon={CheckCircle2}
                        title="You are all caught up"
                        description="New tasks, follow-ups, appointment requests and messages awaiting approval will appear here."
                    />
                ) : null}

                <div className="grid gap-5 xl:grid-cols-2">
                    <Section
                        title="Tasks"
                        description="Open tasks assigned to you"
                        icon={ClipboardCheck}
                        items={sections.tasks}
                        emptyText="No open tasks."
                        dueLabel="Due "
                    />
                    <Section
                        title="Follow-ups"
                        description="Patient follow-ups assigned to you"
                        icon={Repeat}
                        items={sections.follow_ups}
                        emptyText="No follow-ups due."
                        dueLabel="Due "
                    />
                    <Section
                        title="Appointments to confirm"
                        description="Proposed or awaiting hospital confirmation"
                        icon={CalendarClock}
                        items={sections.appointments}
                        emptyText="No appointments waiting."
                        dueLabel="Proposed "
                    />
                    {canApproveMessages ? (
                        <Section
                            title="Messages awaiting approval"
                            description="Drafts that must be approved before they reach a patient"
                            icon={MessageSquareText}
                            items={sections.messages}
                            emptyText="No drafts waiting."
                            dueLabel="Created "
                        />
                    ) : null}
                </div>
            </div>
        </>
    );
}
