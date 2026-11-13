import { router, useForm } from '@inertiajs/react';
import {
    Check,
    Globe,
    Lock,
    Mail,
    MessageCircle,
    NotebookPen,
    Pencil,
    Phone,
    Send,
    Sparkles,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { AppSelect } from '@/components/app-select';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

export type ConversationMessage = {
    uuid: string;
    channel: string;
    channel_label: string;
    direction: string;
    status: string;
    status_label: string;
    subject: string | null;
    body: string;
    recipient: string | null;
    is_ai_draft: boolean;
    requires_approval: boolean;
    created_by: string | null;
    approved_by: string | null;
    created_at: string | null;
    sent_at: string | null;
    approved_at: string | null;
    sender: string | null;
    approve_url: string;
    update_url: string;
};

type Props = {
    patientName: string;
    patientEmail: string | null;
    messages: ConversationMessage[];
    storeUrl: string;
    canManage: boolean;
    aiDraftingAvailable: boolean;
};

type Mode = 'reply' | 'note' | 'log';

const channelIcon: Record<string, typeof Mail> = {
    email: Mail,
    patient_portal: Globe,
    whatsapp: MessageCircle,
    internal: Lock,
    other: Phone,
};

const channelName: Record<string, string> = {
    email: 'Email',
    patient_portal: 'Patient portal',
    whatsapp: 'WhatsApp',
    internal: 'Internal note',
    other: 'Phone or other',
};

function formatTime(value: string | null): string {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatDay(value: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);

    if (date.toDateString() === today.toDateString()) {
        return 'Today';
    }

    if (date.toDateString() === yesterday.toDateString()) {
        return 'Yesterday';
    }

    return date.toLocaleDateString([], {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

const lightOutline =
    'rounded-full border-slate-200 bg-white text-slate-700 hover:border-brand/40 hover:bg-slate-50 hover:text-slate-900';

function isDraft(message: ConversationMessage): boolean {
    return message.status === 'draft' || message.status === 'awaiting_approval';
}

/**
 * The case conversation with the patient, shown like a chat: patient messages on
 * the left, staff messages on the right, internal notes inline, and drafts that
 * a staff member approves and sends in one step.
 */
export function CaseConversation({
    patientName,
    patientEmail,
    messages,
    storeUrl,
    canManage,
    aiDraftingAvailable,
}: Props) {
    const ordered = useMemo(
        () =>
            [...messages].sort(
                (a, b) =>
                    new Date(a.created_at ?? 0).getTime() -
                    new Date(b.created_at ?? 0).getTime(),
            ),
        [messages],
    );
    const scrollRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const element = scrollRef.current;

        if (element) {
            element.scrollTop = element.scrollHeight;
        }
    }, [ordered.length]);

    const draftCount = ordered.filter(isDraft).length;
    const lastPatientFacing = [...ordered]
        .reverse()
        .find((message) => message.channel !== 'internal' && !isDraft(message));
    const waitingForReply = lastPatientFacing?.direction === 'inbound';

    return (
        <section className="light-surface overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <header className="flex flex-wrap items-center gap-3 border-b border-slate-200 px-5 py-4">
                <div className="flex size-10 items-center justify-center rounded-full bg-brand/15 text-sm font-semibold text-brand">
                    {patientName
                        .split(' ')
                        .map((part) => part[0])
                        .slice(0, 2)
                        .join('')
                        .toUpperCase()}
                </div>
                <div className="min-w-0 flex-1">
                    <h2 className="text-base font-semibold text-slate-900">
                        Conversation with {patientName}
                    </h2>
                    <p className="text-xs text-slate-500">
                        Email and patient portal. Replies from the patient
                        portal appear here.
                    </p>
                </div>
                {waitingForReply ? (
                    <span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-900">
                        Patient is waiting for a reply
                    </span>
                ) : null}
                {draftCount > 0 ? (
                    <span className="rounded-full border border-dashed border-amber-400 px-3 py-1 text-xs font-medium text-amber-900">
                        {draftCount} {draftCount === 1 ? 'draft' : 'drafts'} to
                        approve
                    </span>
                ) : null}
            </header>

            <div
                ref={scrollRef}
                className="max-h-[560px] min-h-48 space-y-4 overflow-y-auto bg-[#f6f7f3] px-4 py-5 sm:px-6"
            >
                {ordered.length === 0 ? (
                    <div className="py-10 text-center text-sm text-slate-500">
                        <MessageCircle className="mx-auto mb-2 size-6 text-slate-300" />
                        No messages yet. Write to the patient or add an internal
                        note below.
                    </div>
                ) : (
                    ordered.map((message, index) => {
                        const day = formatDay(message.created_at);
                        const previousDay =
                            index > 0
                                ? formatDay(ordered[index - 1].created_at)
                                : null;

                        return (
                            <div key={message.uuid} className="space-y-4">
                                {day !== previousDay ? (
                                    <div className="text-center text-[11px] font-medium tracking-wide text-slate-400 uppercase">
                                        {day}
                                    </div>
                                ) : null}
                                <MessageRow
                                    message={message}
                                    canManage={canManage}
                                />
                            </div>
                        );
                    })
                )}
            </div>

            {canManage ? (
                <Composer
                    patientEmail={patientEmail}
                    storeUrl={storeUrl}
                    aiDraftingAvailable={aiDraftingAvailable}
                />
            ) : null}
        </section>
    );
}

function MessageRow({
    message,
    canManage,
}: {
    message: ConversationMessage;
    canManage: boolean;
}) {
    const Icon = channelIcon[message.channel] ?? MessageCircle;
    const channel = channelName[message.channel] ?? message.channel_label;

    if (message.channel === 'internal') {
        return (
            <div className="mx-auto max-w-[85%] rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-950">
                <div className="mb-1 flex items-center gap-1.5 text-xs font-medium text-amber-800">
                    <Lock className="size-3.5" />
                    Internal note
                    {message.sender ? ` from ${message.sender}` : ''} · only
                    staff can see this
                    <span className="ms-auto font-normal text-amber-700">
                        {formatTime(message.created_at)}
                    </span>
                </div>
                <p className="whitespace-pre-wrap">{message.body}</p>
            </div>
        );
    }

    const fromPatient = message.direction === 'inbound';

    if (isDraft(message)) {
        return <DraftBubble message={message} canManage={canManage} />;
    }

    let meta: string;

    if (fromPatient) {
        meta =
            message.channel === 'patient_portal'
                ? `${message.sender ?? 'Patient'} · via patient portal`
                : `${message.sender ?? 'Patient'} · ${channel}${message.created_by ? ` · logged by ${message.created_by}` : ''}`;
    } else if (message.status === 'sent') {
        meta = `Sent by ${message.approved_by ?? message.created_by ?? 'staff'} · ${channel}`;
    } else if (message.status === 'logged') {
        meta = `Logged by ${message.created_by ?? 'staff'} · ${channel} · not sent from CareFlow`;
    } else if (message.status === 'approved') {
        meta = `Approved by ${message.approved_by ?? 'staff'} · not sent yet`;
    } else {
        meta = `${message.status_label} · ${channel}`;
    }

    return (
        <div
            className={cn(
                'flex flex-col',
                fromPatient ? 'items-start' : 'items-end',
            )}
        >
            <div
                className={cn(
                    'max-w-[85%] px-4 py-3 text-sm leading-6 sm:max-w-[72%]',
                    fromPatient
                        ? 'rounded-[18px] rounded-bl-md border border-slate-200 bg-white text-slate-800'
                        : 'rounded-[18px] rounded-br-md bg-[#dfe8d2] text-[#1d3320]',
                    message.status === 'failed' &&
                        'border border-rose-300 bg-rose-50 text-rose-900',
                )}
            >
                {message.subject ? (
                    <p className="mb-1 font-semibold">{message.subject}</p>
                ) : null}
                <p className="whitespace-pre-wrap">{message.body}</p>
            </div>
            <div
                className={cn(
                    'mt-1 flex items-center gap-1.5 px-1 text-[11px] text-slate-500',
                    fromPatient ? '' : 'flex-row-reverse',
                )}
            >
                <Icon className="size-3.5" />
                <span>{meta}</span>
                <span>·</span>
                <span>{formatTime(message.sent_at ?? message.created_at)}</span>
                {!fromPatient && message.status === 'sent' ? (
                    <Check className="size-3.5 text-brand" />
                ) : null}
            </div>
            {canManage &&
            message.status === 'approved' &&
            message.channel === 'email' ? (
                <SendButton
                    message={message}
                    label="Send now"
                    className="mt-2"
                />
            ) : null}
        </div>
    );
}

function SendButton({
    message,
    label,
    className,
}: {
    message: ConversationMessage;
    label: string;
    className?: string;
}) {
    const [sending, setSending] = useState(false);

    return (
        <Button
            type="button"
            size="sm"
            className={cn(
                'rounded-full bg-brand text-white hover:bg-brand/90',
                className,
            )}
            disabled={sending}
            onClick={() =>
                router.post(
                    message.approve_url,
                    { mark_sent: true },
                    {
                        preserveScroll: true,
                        onStart: () => setSending(true),
                        onFinish: () => setSending(false),
                    },
                )
            }
        >
            <Send className="size-3.5" />
            {sending ? 'Sending…' : label}
        </Button>
    );
}

function DraftBubble({
    message,
    canManage,
}: {
    message: ConversationMessage;
    canManage: boolean;
}) {
    const [editing, setEditing] = useState(false);
    const form = useForm({
        subject: message.subject ?? '',
        body: message.body,
    });
    const Icon = channelIcon[message.channel] ?? MessageCircle;
    const channel = channelName[message.channel] ?? message.channel_label;
    const destination =
        message.channel === 'email'
            ? `Will be emailed to ${message.recipient ?? 'the patient'}`
            : message.channel === 'patient_portal'
              ? 'Will appear on the patient’s portal page'
              : `Will be recorded as ${channel}`;

    return (
        <div className="flex flex-col items-end">
            <div className="w-full max-w-[85%] rounded-[18px] rounded-br-md border-2 border-dashed border-amber-300 bg-white px-4 py-3 text-sm sm:max-w-[72%]">
                <div className="mb-2 flex flex-wrap items-center gap-2 text-xs">
                    <span className="rounded-full bg-amber-100 px-2 py-0.5 font-medium text-amber-900">
                        Draft · needs approval
                    </span>
                    {message.is_ai_draft ? (
                        <span className="inline-flex items-center gap-1 rounded-full bg-[#ece9f8] px-2 py-0.5 font-medium text-[#4b3f8f]">
                            <Sparkles className="size-3" />
                            Written by AI
                        </span>
                    ) : null}
                </div>

                {editing ? (
                    <form
                        className="space-y-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.patch(message.update_url, {
                                preserveScroll: true,
                                onSuccess: () => setEditing(false),
                            });
                        }}
                    >
                        {message.channel === 'email' ? (
                            <input
                                className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm"
                                placeholder="Subject"
                                value={form.data.subject}
                                onChange={(event) =>
                                    form.setData('subject', event.target.value)
                                }
                            />
                        ) : null}
                        <Textarea
                            className="min-h-32 bg-white"
                            value={form.data.body}
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                        />
                        <InputError message={form.errors.body} />
                        <div className="flex justify-end gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                className="text-slate-600 hover:bg-slate-100 hover:text-slate-900"
                                onClick={() => setEditing(false)}
                            >
                                <X className="size-3.5" />
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                size="sm"
                                disabled={form.processing}
                            >
                                <Check className="size-3.5" />
                                Save changes
                            </Button>
                        </div>
                    </form>
                ) : (
                    <>
                        {message.subject ? (
                            <p className="mb-1 font-semibold text-slate-900">
                                {message.subject}
                            </p>
                        ) : null}
                        <p className="whitespace-pre-wrap text-slate-700">
                            {message.body}
                        </p>
                    </>
                )}
            </div>
            <div className="mt-1 flex items-center gap-1.5 px-1 text-[11px] text-slate-500">
                <Icon className="size-3.5" />
                <span>
                    {destination}
                    {message.created_by
                        ? ` · drafted by ${message.created_by}`
                        : ''}
                </span>
            </div>
            {canManage && !editing ? (
                <div className="mt-2 flex flex-wrap justify-end gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className={lightOutline}
                        onClick={() => setEditing(true)}
                    >
                        <Pencil className="size-3.5" />
                        Edit
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className={lightOutline}
                        onClick={() => {
                            if (
                                window.confirm(
                                    'Discard this draft? It will not be sent.',
                                )
                            ) {
                                router.delete(message.update_url, {
                                    preserveScroll: true,
                                });
                            }
                        }}
                    >
                        <Trash2 className="size-3.5" />
                        Discard
                    </Button>
                    <SendButton
                        message={message}
                        label={
                            message.channel === 'email'
                                ? 'Approve and send'
                                : 'Approve and share'
                        }
                    />
                </div>
            ) : null}
        </div>
    );
}

function Composer({
    patientEmail,
    storeUrl,
    aiDraftingAvailable,
}: {
    patientEmail: string | null;
    storeUrl: string;
    aiDraftingAvailable: boolean;
}) {
    const [mode, setMode] = useState<Mode>('reply');
    const [aiOpen, setAiOpen] = useState(false);
    const defaultReplyChannel = patientEmail ? 'email' : 'patient_portal';
    const form = useForm({
        reply_channel: defaultReplyChannel,
        log_channel: 'whatsapp',
        log_direction: 'inbound',
        subject: '',
        body: '',
        ai_brief: '',
    });

    const replyChannels = [
        ...(patientEmail
            ? [{ value: 'email', label: `Email · ${patientEmail}` }]
            : []),
        { value: 'patient_portal', label: 'Patient portal page' },
    ];

    const submit = (intent: 'send' | 'draft' | 'ai') => {
        form.transform((data) => {
            if (mode === 'note') {
                return { channel: 'internal', body: data.body };
            }

            if (mode === 'log') {
                return {
                    channel: data.log_channel,
                    direction: data.log_direction,
                    body: data.body,
                    log_only: true,
                };
            }

            if (intent === 'ai') {
                return {
                    channel: data.reply_channel,
                    draft_with_ai: true,
                    ai_brief: data.ai_brief,
                    subject: data.subject || null,
                };
            }

            return {
                channel: data.reply_channel,
                subject: data.subject || null,
                body: data.body,
                send_now: intent === 'send',
            };
        });

        form.post(storeUrl, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('body', 'subject', 'ai_brief');
                setAiOpen(false);
            },
        });
    };

    const modes: Array<{ id: Mode; label: string; icon: typeof Mail }> = [
        { id: 'reply', label: 'Message the patient', icon: Send },
        { id: 'note', label: 'Internal note', icon: NotebookPen },
        { id: 'log', label: 'Log a call or WhatsApp', icon: Phone },
    ];

    const error =
        form.errors.body ||
        (form.errors as Record<string, string | undefined>).communication ||
        form.errors.ai_brief;

    return (
        <div className="border-t border-slate-200 bg-white px-4 py-4 sm:px-6">
            <div
                className="mb-3 flex flex-wrap gap-1.5"
                role="tablist"
                aria-label="What do you want to add?"
            >
                {modes.map((item) => (
                    <button
                        key={item.id}
                        type="button"
                        role="tab"
                        aria-selected={mode === item.id}
                        onClick={() => {
                            setMode(item.id);
                            setAiOpen(false);
                        }}
                        className={cn(
                            'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition',
                            mode === item.id
                                ? 'bg-brand text-white'
                                : 'border border-slate-200 bg-white text-slate-600 hover:border-brand/40 hover:text-slate-900',
                        )}
                    >
                        <item.icon className="size-3.5" />
                        {item.label}
                    </button>
                ))}
            </div>

            {mode === 'reply' ? (
                <div className="mb-2 grid gap-2 sm:grid-cols-[minmax(0,240px)_minmax(0,1fr)]">
                    <AppSelect
                        aria-label="Send by"
                        value={form.data.reply_channel}
                        onChange={(value) =>
                            form.setData('reply_channel', value)
                        }
                        options={replyChannels}
                    />
                    {form.data.reply_channel === 'email' ? (
                        <input
                            className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm"
                            placeholder="Subject (optional)"
                            value={form.data.subject}
                            onChange={(event) =>
                                form.setData('subject', event.target.value)
                            }
                        />
                    ) : null}
                </div>
            ) : null}

            {mode === 'log' ? (
                <div className="mb-2 grid gap-2 sm:grid-cols-2">
                    <AppSelect
                        aria-label="Channel"
                        value={form.data.log_channel}
                        onChange={(value) => form.setData('log_channel', value)}
                        options={[
                            { value: 'whatsapp', label: 'WhatsApp' },
                            { value: 'other', label: 'Phone call or other' },
                        ]}
                    />
                    <AppSelect
                        aria-label="Who said it"
                        value={form.data.log_direction}
                        onChange={(value) =>
                            form.setData('log_direction', value)
                        }
                        options={[
                            { value: 'inbound', label: 'The patient said' },
                            { value: 'outbound', label: 'We told the patient' },
                        ]}
                    />
                </div>
            ) : null}

            {aiOpen && mode === 'reply' ? (
                <div className="mb-2 rounded-xl border border-[#d9d4f1] bg-[#f6f4fd] p-3">
                    <p className="mb-2 flex items-center gap-1.5 text-xs font-medium text-[#4b3f8f]">
                        <Sparkles className="size-3.5" />
                        Tell AI what the message should say. You review it
                        before anything is sent.
                    </p>
                    <Textarea
                        className="min-h-20 bg-white"
                        placeholder="Confirm we received the scan and that a doctor will review it this week"
                        value={form.data.ai_brief}
                        onChange={(event) =>
                            form.setData('ai_brief', event.target.value)
                        }
                    />
                    <div className="mt-2 flex justify-end gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="text-slate-600 hover:bg-slate-100 hover:text-slate-900"
                            onClick={() => setAiOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            disabled={
                                form.processing ||
                                form.data.ai_brief.trim() === ''
                            }
                            onClick={() => submit('ai')}
                        >
                            <Sparkles className="size-3.5" />
                            {form.processing ? 'Drafting…' : 'Write draft'}
                        </Button>
                    </div>
                    <p className="mt-2 text-[11px] text-[#4b3f8f]/80">
                        AI writes administrative messages only, never medical
                        advice.
                    </p>
                </div>
            ) : null}

            {!aiOpen ? (
                <Textarea
                    className="min-h-24 bg-white"
                    placeholder={
                        mode === 'note'
                            ? 'Write a note for the team. The patient never sees this.'
                            : mode === 'log'
                              ? 'What was said?'
                              : 'Write your message to the patient'
                    }
                    value={form.data.body}
                    onChange={(event) =>
                        form.setData('body', event.target.value)
                    }
                />
            ) : null}

            <InputError className="mt-2" message={error} />

            {!aiOpen ? (
                <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                    <p className="text-xs text-slate-500">
                        {mode === 'reply'
                            ? 'Administrative messages only. Clinical questions go to a doctor.'
                            : mode === 'note'
                              ? 'Visible to staff on this case only.'
                              : 'Kept as a record. Nothing is sent from CareFlow.'}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {mode === 'reply' && aiDraftingAvailable ? (
                            <Button
                                type="button"
                                variant="outline"
                                className={lightOutline}
                                onClick={() => setAiOpen(true)}
                            >
                                <Sparkles className="size-4" />
                                Draft with AI
                            </Button>
                        ) : null}
                        {mode === 'reply' ? (
                            <Button
                                type="button"
                                variant="outline"
                                className={lightOutline}
                                disabled={
                                    form.processing ||
                                    form.data.body.trim() === ''
                                }
                                onClick={() => submit('draft')}
                            >
                                Save as draft
                            </Button>
                        ) : null}
                        <Button
                            type="button"
                            className="rounded-full bg-brand text-white hover:bg-brand/90"
                            disabled={
                                form.processing || form.data.body.trim() === ''
                            }
                            onClick={() => submit('send')}
                        >
                            {mode === 'reply' ? (
                                <Send className="size-4" />
                            ) : (
                                <Check className="size-4" />
                            )}
                            {mode === 'reply'
                                ? form.processing
                                    ? 'Sending…'
                                    : 'Send'
                                : mode === 'note'
                                  ? 'Add note'
                                  : 'Save to conversation'}
                        </Button>
                    </div>
                </div>
            ) : null}
        </div>
    );
}
