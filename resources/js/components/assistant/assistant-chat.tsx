import { Link } from '@inertiajs/react';
import {
    Check,
    CheckCircle2,
    CircleAlert,
    RotateCcw,
    SendHorizontal,
    ShieldCheck,
    X,
    XCircle,
} from 'lucide-react';
import {
    Fragment,
    useEffect,
    useRef,
    useState,
    type KeyboardEvent,
    type ReactNode,
} from 'react';
import type {
    AssistantApproval,
    AssistantMessage,
    useAssistant,
} from '@/hooks/use-assistant';
import { cn } from '@/lib/utils';

export function AssistantAvatar({
    className,
    online = true,
}: {
    className?: string;
    online?: boolean;
}) {
    return (
        <span className={cn('relative inline-flex shrink-0', className)}>
            <img
                src="/images/assistant-avatar.svg?v=2"
                alt=""
                draggable={false}
                className="pointer-events-none size-full rounded-full ring-2 ring-card select-none"
            />
            {online ? (
                <span className="pointer-events-none absolute right-0 bottom-0 size-[26%] min-h-2 min-w-2 rounded-full bg-emerald-500 ring-2 ring-card" />
            ) : null}
        </span>
    );
}

const CASE_REFERENCE = /\b([A-Z]{2,5}-\d{4}-\d{6})\b/g;

/**
 * A markdown link from the assistant. Links inside the app open in place;
 * anything that is not http(s) or a relative path is shown as plain text.
 */
function SafeLink({ label, href }: { label: string; href: string }) {
    const className =
        'font-medium text-brand underline-offset-2 hover:underline';
    let url: URL | null = null;

    try {
        url = new URL(href, window.location.origin);
    } catch {
        url = null;
    }

    if (url === null || !['http:', 'https:'].includes(url.protocol)) {
        return <>{label}</>;
    }

    if (href.startsWith('/') || url.origin === window.location.origin) {
        return (
            <Link href={`${url.pathname}${url.search}`} className={className}>
                {label}
            </Link>
        );
    }

    return (
        <a href={url.toString()} target="_blank" rel="noopener noreferrer" className={className}>
            {label}
        </a>
    );
}

/** Inline formatting: **bold**, `code`, and clickable case references. */
function inline(text: string, slug: string): ReactNode[] {
    const nodes: ReactNode[] = [];
    const parts = text.split(/(\[[^\]]+\]\([^)\s]+\)|\*\*[^*]+\*\*|`[^`]+`|\*[^*\s][^*]*\*)/g);

    parts.forEach((part, index) => {
        const link = part.match(/^\[([^\]]+)\]\(([^)\s]+)\)$/);

        if (link) {
            nodes.push(<SafeLink key={index} label={link[1]} href={link[2]} />);

            return;
        }

        if (part.startsWith('**') && part.endsWith('**') && part.length > 4) {
            nodes.push(
                <strong key={index} className="font-semibold">
                    {inline(part.slice(2, -2), slug)}
                </strong>,
            );

            return;
        }

        if (part.startsWith('*') && part.endsWith('*') && part.length > 2) {
            nodes.push(
                <em key={index} className="text-muted-foreground">
                    {inline(part.slice(1, -1), slug)}
                </em>,
            );

            return;
        }

        if (part.startsWith('`') && part.endsWith('`') && part.length > 2) {
            nodes.push(
                <code key={index} className="rounded bg-muted px-1 py-0.5 text-[0.85em]">
                    {part.slice(1, -1)}
                </code>,
            );

            return;
        }

        const pieces = part.split(CASE_REFERENCE);

        pieces.forEach((piece, pieceIndex) => {
            if (pieceIndex % 2 === 1) {
                nodes.push(
                    <Link
                        key={`${index}-${pieceIndex}`}
                        href={`/${slug}/cases/${piece}`}
                        className="font-mono text-[0.92em] font-medium text-brand underline-offset-2 hover:underline"
                    >
                        {piece}
                    </Link>,
                );
            } else if (piece !== '') {
                nodes.push(<Fragment key={`${index}-${pieceIndex}`}>{piece}</Fragment>);
            }
        });
    });

    return nodes;
}

/** A small, safe renderer for the assistant's replies: paragraphs, lists and headings. */
function RichText({ text, slug }: { text: string; slug: string }) {
    const blocks: ReactNode[] = [];
    let list: { ordered: boolean; items: string[] } | null = null;

    const flush = () => {
        if (list === null) {
            return;
        }

        const ListTag = list.ordered ? 'ol' : 'ul';

        blocks.push(
            <ListTag
                key={`list-${blocks.length}`}
                className={cn(
                    'space-y-1 pl-5',
                    list.ordered ? 'list-decimal' : 'list-disc',
                )}
            >
                {list.items.map((item, index) => (
                    <li key={index}>{inline(item, slug)}</li>
                ))}
            </ListTag>,
        );
        list = null;
    };

    text.split('\n').forEach((raw) => {
        const line = raw.trimEnd();
        const bullet = line.match(/^\s*[-*•]\s+(.*)$/);
        const numbered = line.match(/^\s*\d+[.)]\s+(.*)$/);
        const heading = line.match(/^#{1,4}\s+(.*)$/);

        if (bullet || numbered) {
            const ordered = numbered !== null;

            if (list === null || list.ordered !== ordered) {
                flush();
                list = { ordered, items: [] };
            }

            list.items.push((bullet ?? numbered)![1]);

            return;
        }

        flush();

        if (line.trim() === '') {
            return;
        }

        if (heading) {
            blocks.push(
                <p key={`h-${blocks.length}`} className="font-semibold">
                    {inline(heading[1], slug)}
                </p>,
            );

            return;
        }

        blocks.push(<p key={`p-${blocks.length}`}>{inline(line, slug)}</p>);
    });

    flush();

    return <div className="space-y-2">{blocks}</div>;
}

function timeOf(value: string | null): string {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function MessageBubble({
    message,
    slug,
    compact,
}: {
    message: AssistantMessage;
    slug: string;
    compact: boolean;
}) {
    const mine = message.role === 'user';

    return (
        <div className={cn('flex items-end gap-2.5', mine && 'justify-end')}>
            {!mine ? (
                <AssistantAvatar
                    online={false}
                    className={compact ? 'size-7' : 'size-8'}
                />
            ) : null}
            <div
                className={cn(
                    'max-w-[85%] rounded-2xl px-4 py-3 text-sm leading-relaxed',
                    mine
                        ? 'rounded-br-md bg-brand text-brand-foreground'
                        : 'rounded-bl-md border border-border bg-card text-foreground shadow-sm',
                )}
            >
                {message.actions.length > 0 ? (
                    <div className="mb-2 flex flex-wrap gap-1.5">
                        {message.actions.map((action, index) => (
                            <span
                                key={index}
                                className={cn(
                                    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium',
                                    action.outcome === 'done' &&
                                        'bg-accent text-accent-foreground',
                                    action.outcome === 'declined' &&
                                        'bg-muted text-muted-foreground',
                                    action.outcome === 'failed' &&
                                        'bg-destructive/10 text-destructive',
                                )}
                            >
                                {action.outcome === 'done' ? (
                                    <CheckCircle2 className="size-3" />
                                ) : (
                                    <XCircle className="size-3" />
                                )}
                                {action.label}
                                {action.outcome === 'declined' ? ' · declined' : ''}
                            </span>
                        ))}
                    </div>
                ) : null}
                {message.content.trim() !== '' ? (
                    mine ? (
                        <p className="whitespace-pre-wrap">{message.content}</p>
                    ) : (
                        <RichText text={message.content} slug={slug} />
                    )
                ) : null}
                <p
                    className={cn(
                        'mt-1.5 text-right text-[10px]',
                        mine ? 'text-brand-foreground/70' : 'text-muted-foreground',
                    )}
                >
                    {timeOf(message.created_at)}
                </p>
            </div>
        </div>
    );
}

function ApprovalCard({
    approval,
    busy,
    onDecide,
}: {
    approval: AssistantApproval;
    busy: boolean;
    onDecide: (approve: boolean) => void;
}) {
    return (
        <div className="rounded-2xl border border-amber-300/70 bg-amber-50 p-4 text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
            <div className="flex items-start gap-3">
                <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200">
                    <ShieldCheck className="size-4" />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="text-xs font-semibold tracking-wide uppercase opacity-80">
                        Needs your approval · {approval.label}
                    </p>
                    <p className="mt-1 text-sm font-medium">{approval.summary}</p>
                    <div className="mt-3 flex flex-wrap gap-2">
                        <button
                            type="button"
                            disabled={busy}
                            onClick={() => onDecide(true)}
                            className="inline-flex items-center gap-1.5 rounded-full bg-brand px-4 py-1.5 text-xs font-semibold text-brand-foreground transition hover:bg-brand/90 disabled:opacity-50"
                        >
                            <Check className="size-3.5" />
                            Approve
                        </button>
                        <button
                            type="button"
                            disabled={busy}
                            onClick={() => onDecide(false)}
                            className="inline-flex items-center gap-1.5 rounded-full border border-amber-300 bg-white/70 px-4 py-1.5 text-xs font-semibold text-amber-900 transition hover:bg-white disabled:opacity-50 dark:border-amber-500/40 dark:bg-transparent dark:text-amber-100"
                        >
                            <X className="size-3.5" />
                            Decline
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

export const DEFAULT_SUGGESTIONS = [
    'How does my pipeline look?',
    'What is on my plate today?',
    'Which cases are unassigned?',
    'Which documents need review?',
];

type ChatProps = {
    slug: string;
    name: string;
    assistant: ReturnType<typeof useAssistant>;
    compact?: boolean;
    suggestions?: string[];
};

/**
 * The conversation, approvals and composer. Used full-size on the command center
 * page and compact inside the floating widget.
 */
export function AssistantChat({
    slug,
    name,
    assistant,
    compact = false,
    suggestions = DEFAULT_SUGGESTIONS,
}: ChatProps) {
    const [draft, setDraft] = useState('');
    const scroller = useRef<HTMLDivElement>(null);
    const { messages, pending, busy, error, send, decide } = assistant;

    useEffect(() => {
        const element = scroller.current;

        if (element) {
            element.scrollTo({ top: element.scrollHeight, behavior: 'smooth' });
        }
    }, [messages.length, pending.length, busy]);

    const submit = () => {
        if (draft.trim() === '' || busy) {
            return;
        }

        send(draft);
        setDraft('');
    };

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            submit();
        }
    };

    const empty = messages.length === 0 && pending.length === 0;

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <div
                ref={scroller}
                className={cn(
                    'min-h-0 flex-1 space-y-4 overflow-y-auto bg-muted/40',
                    compact ? 'p-4' : 'p-5 md:p-6',
                )}
            >
                {empty ? (
                    <div className="flex h-full flex-col items-center justify-center gap-4 py-8 text-center">
                        <AssistantAvatar className={compact ? 'size-14' : 'size-20'} />
                        <div className="max-w-sm space-y-1">
                            <p className="font-semibold">Hi, I&rsquo;m {name}.</p>
                            <p className="text-sm text-muted-foreground">
                                Ask me about your cases, your work for today or
                                the pipeline. I can also assign cases, change
                                statuses and create tasks once you approve.
                            </p>
                        </div>
                        <div className="flex flex-wrap justify-center gap-2">
                            {suggestions.map((suggestion) => (
                                <button
                                    key={suggestion}
                                    type="button"
                                    onClick={() => send(suggestion)}
                                    className="rounded-full border border-border bg-card px-3 py-1.5 text-xs font-medium text-foreground transition hover:border-brand/40 hover:bg-accent"
                                >
                                    {suggestion}
                                </button>
                            ))}
                        </div>
                    </div>
                ) : (
                    messages.map((message) => (
                        <MessageBubble
                            key={message.id}
                            message={message}
                            slug={slug}
                            compact={compact}
                        />
                    ))
                )}

                {pending.map((approval) => (
                    <ApprovalCard
                        key={approval.id}
                        approval={approval}
                        busy={busy}
                        onDecide={(approve) => void decide(approval.id, approve)}
                    />
                ))}

                {busy ? (
                    <div className="flex items-center gap-2.5">
                        <AssistantAvatar online={false} className="size-7" />
                        <div className="flex gap-1 rounded-2xl rounded-bl-md border border-border bg-card px-4 py-3 shadow-sm">
                            {[0, 1, 2].map((dot) => (
                                <span
                                    key={dot}
                                    className="size-1.5 animate-bounce rounded-full bg-muted-foreground/60"
                                    style={{ animationDelay: `${dot * 150}ms` }}
                                />
                            ))}
                        </div>
                    </div>
                ) : null}

                {error ? (
                    <div className="flex items-start gap-2 rounded-xl border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive">
                        <CircleAlert className="mt-0.5 size-4 shrink-0" />
                        {error}
                    </div>
                ) : null}
            </div>

            <div className={cn('border-t border-border bg-card', compact ? 'p-3' : 'p-4')}>
                {!empty && !compact ? (
                    <div className="mb-3 flex gap-2 overflow-x-auto [scrollbar-width:none]">
                        {suggestions.map((suggestion) => (
                            <button
                                key={suggestion}
                                type="button"
                                disabled={busy}
                                onClick={() => send(suggestion)}
                                className="shrink-0 rounded-full border border-border px-3 py-1 text-xs text-muted-foreground transition hover:border-brand/40 hover:text-foreground disabled:opacity-50"
                            >
                                {suggestion}
                            </button>
                        ))}
                    </div>
                ) : null}
                <div className="flex items-end gap-2 rounded-2xl border border-input bg-background p-2 focus-within:border-brand/50 focus-within:ring-2 focus-within:ring-brand/15">
                    <textarea
                        id={compact ? 'assistant-widget-input' : 'assistant-input'}
                        value={draft}
                        onChange={(event) => setDraft(event.target.value)}
                        onKeyDown={onKeyDown}
                        rows={compact ? 1 : 2}
                        maxLength={2000}
                        placeholder={`Message ${name}…`}
                        aria-label={`Message ${name}`}
                        className="max-h-40 min-h-9 flex-1 resize-none bg-transparent px-2 py-1.5 text-sm outline-none placeholder:text-muted-foreground"
                    />
                    <button
                        type="button"
                        onClick={submit}
                        disabled={busy || draft.trim() === ''}
                        aria-label="Send message"
                        className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand text-brand-foreground transition hover:bg-brand/90 disabled:opacity-40"
                    >
                        <SendHorizontal className="size-4" />
                    </button>
                </div>
                <p className="mt-2 text-center text-[11px] text-muted-foreground">
                    {name} can make mistakes. Actions always wait for your approval.
                </p>
            </div>
        </div>
    );
}

export function ClearChatButton({
    onClear,
    disabled,
}: {
    onClear: () => void;
    disabled: boolean;
}) {
    const [confirming, setConfirming] = useState(false);

    if (confirming) {
        return (
            <span className="inline-flex items-center gap-1.5 text-xs">
                <span className="text-muted-foreground">Start over?</span>
                <button
                    type="button"
                    onClick={() => {
                        onClear();
                        setConfirming(false);
                    }}
                    className="rounded-full bg-destructive/10 px-2.5 py-1 font-semibold text-destructive hover:bg-destructive/15"
                >
                    Clear
                </button>
                <button
                    type="button"
                    onClick={() => setConfirming(false)}
                    className="rounded-full px-2 py-1 text-muted-foreground hover:text-foreground"
                >
                    Keep
                </button>
            </span>
        );
    }

    return (
        <button
            type="button"
            disabled={disabled}
            onClick={() => setConfirming(true)}
            className="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground disabled:opacity-50"
        >
            <RotateCcw className="size-3.5" />
            Clear chat
        </button>
    );
}
