import { Head, Link, router } from '@inertiajs/react';
import {
    CalendarClock,
    Check,
    CheckCircle2,
    ClipboardCheck,
    FileSearch,
    Loader2,
    MessageCircleReply,
    MessageSquareText,
    Repeat,
    Stethoscope,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
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
    url?: string;
    complete_url?: string | null;
};

type Props = {
    team: { id: number; name: string; slug: string };
    sections: {
        tasks: WorkItem[];
        follow_ups: WorkItem[];
        appointments: WorkItem[];
        messages: WorkItem[];
        replies: WorkItem[];
        documents: WorkItem[];
        approvals: WorkItem[];
    };
    canApproveMessages: boolean;
    canSeeReplies: boolean;
    canReviewDocuments: boolean;
    canApproveClinical: boolean;
    attentionCount: number;
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

function TickButton({ item, kind }: { item: WorkItem; kind: string }) {
    const [processing, setProcessing] = useState(false);

    if (!item.complete_url) {
        return null;
    }

    const completeUrl = item.complete_url;

    return (
        <button
            type="button"
            disabled={processing}
            onClick={() =>
                router.post(
                    completeUrl,
                    {},
                    {
                        preserveScroll: true,
                        onStart: () => setProcessing(true),
                        onFinish: () => setProcessing(false),
                        onError: (errors) =>
                            toast.error(
                                Object.values(errors)[0] ??
                                    'That could not be saved. Please try again.',
                            ),
                    },
                )
            }
            className={cn(
                'group/tick flex size-6 shrink-0 items-center justify-center rounded-full border-2 border-slate-300 bg-white text-transparent transition',
                'hover:border-brand hover:text-brand focus-visible:ring-2 focus-visible:ring-brand/40 focus-visible:outline-none',
                'disabled:cursor-wait disabled:border-brand disabled:bg-brand disabled:text-white',
            )}
            aria-label={`Mark ${kind} "${item.title}" as done`}
            title={`Mark as done`}
        >
            {processing ? (
                <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
            ) : (
                <Check className="size-3.5 stroke-[3]" aria-hidden="true" />
            )}
        </button>
    );
}

function Section({
    title,
    description,
    icon: Icon,
    items,
    emptyText,
    dueLabel,
    tickKind,
}: {
    title: string;
    description: string;
    icon: LucideIcon;
    items: WorkItem[];
    emptyText: string;
    dueLabel: string;
    tickKind?: string;
}) {
    return (
        <section className="light-surface overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_10px_30px_-24px_rgba(15,23,42,0.35)]">
            <div className="flex items-center gap-2.5 border-b border-slate-200/80 px-5 py-4">
                <span className="flex size-8 items-center justify-center rounded-lg bg-brand/10 text-brand">
                    <Icon className="size-4" aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1">
                    <h2 className="text-sm font-semibold text-slate-900">
                        {title}
                        <span className="ms-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">
                            {items.length}
                        </span>
                    </h2>
                    <p className="text-xs text-slate-500">{description}</p>
                </div>
            </div>
            {items.length === 0 ? (
                <p className="flex items-center gap-2 px-5 py-6 text-sm text-slate-500">
                    <CheckCircle2
                        className="size-4 text-emerald-600"
                        aria-hidden="true"
                    />
                    {emptyText}
                </p>
            ) : (
                <ul className="divide-y divide-slate-200/80">
                    {items.map((item) => (
                        <li
                            key={item.id}
                            className="flex items-center gap-3 px-5 hover:bg-slate-50"
                        >
                            {tickKind ? (
                                <TickButton item={item} kind={tickKind} />
                            ) : null}
                            <Link
                                href={
                                    item.url ??
                                    `${item.case.url}?tab=${item.tab}`
                                }
                                className="flex min-w-0 flex-1 flex-wrap items-center justify-between gap-2 py-3 text-sm"
                            >
                                <div className="min-w-0">
                                    <p className="truncate font-medium text-slate-900">
                                        {item.title}
                                    </p>
                                    <p className="truncate text-xs text-slate-500">
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
                                            : 'text-slate-500',
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

export default function Work({
    sections,
    canApproveMessages,
    canSeeReplies,
    canReviewDocuments,
    canApproveClinical,
    attentionCount,
}: Props) {
    const total = attentionCount;
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
                        description="New tasks, follow-ups, patient messages, documents to check and anything waiting for your approval will appear here."
                    />
                ) : null}

                <div className="grid gap-5 xl:grid-cols-2">
                    {canApproveClinical ? (
                        <Section
                            title="Waiting for your approval"
                            description="Treatment proposals and clinical recommendations that need your sign-off"
                            icon={Stethoscope}
                            items={sections.approvals}
                            emptyText="Nothing waiting for your approval."
                            dueLabel="Sent "
                        />
                    ) : null}
                    {canSeeReplies ? (
                        <Section
                            title="Patients waiting for a reply"
                            description="The patient wrote last and nobody has answered yet"
                            icon={MessageCircleReply}
                            items={sections.replies}
                            emptyText="No patients waiting for a reply."
                            dueLabel="Received "
                        />
                    ) : null}
                    <Section
                        title="Tasks"
                        description="Open tasks assigned to you. Tick the circle when a task is done."
                        icon={ClipboardCheck}
                        items={sections.tasks}
                        emptyText="No open tasks."
                        dueLabel="Due "
                        tickKind="task"
                    />
                    <Section
                        title="Follow-ups"
                        description="Patient follow-ups assigned to you. Tick the circle when it is done."
                        icon={Repeat}
                        items={sections.follow_ups}
                        emptyText="No follow-ups due."
                        dueLabel="Due "
                        tickKind="follow-up"
                    />
                    {canReviewDocuments ? (
                        <Section
                            title="Documents to review"
                            description="Read and confirm what was pulled out of these documents"
                            icon={FileSearch}
                            items={sections.documents}
                            emptyText="No documents waiting for review."
                            dueLabel="Uploaded "
                        />
                    ) : null}
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
