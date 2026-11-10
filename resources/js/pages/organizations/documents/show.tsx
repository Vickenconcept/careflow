import { Head, Link, router, useForm, usePoll } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    Bot,
    CheckCircle2,
    Download,
    FileText,
    History,
    Languages,
    LoaderCircle,
    PencilLine,
    RefreshCw,
    Save,
    ShieldAlert,
    Sparkles,
} from 'lucide-react';
import { type ReactNode, useEffect, useMemo, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type ProcessingStep = {
    key: string;
    label: string;
    state: 'complete' | 'current' | 'upcoming' | 'failed';
};

type StatusOption = {
    value: string;
    label: string;
};

type Props = {
    team: { id: number; name: string; slug: string };
    case: {
        reference: string;
        status: string;
        status_label: string;
        show_url: string;
        status_update_url: string;
    };
    availableStatusTransitions: StatusOption[];
    document: {
        uuid: string;
        original_filename: string;
        mime_type: string;
        extension: string;
        category: string;
        processing_status: string;
        processing_status_label: string;
        processing_error: string | null;
        processing_in_progress: boolean;
        processing_steps: ProcessingStep[];
        original_language: string | null;
        uploaded_at: string;
        preview_url: string;
        download_url: string;
        reprocess_url: string;
        review_update_url: string;
        is_image: boolean;
        is_pdf: boolean;
    };
    ai: {
        provider: string;
        disclaimer_summary: string;
        disclaimer_translation: string;
        extracted_text: string | null;
        extraction: {
            version: number;
            payload: Record<string, unknown>;
            original_payload: Record<string, unknown>;
            is_synthetic: boolean;
            review_status: string;
            provider: string;
        } | null;
        translation: {
            version: number;
            source_language: string;
            target_language: string;
            translated_text: string;
            disclaimer: string;
            is_synthetic: boolean;
            provider: string;
        } | null;
        summary: {
            version: number;
            sections: Record<string, unknown>;
            original_sections: Record<string, unknown>;
            disclaimer: string;
            is_synthetic: boolean;
            review_status: string;
            provider: string;
        } | null;
        review_events: Array<{
            id: number;
            field_path: string;
            reason: string | null;
            reviewed_at: string;
        }>;
    };
    permissions: {
        canReviewAi: boolean;
        canDownload: boolean;
        canReprocess: boolean;
        canUpdateStatus: boolean;
    };
};

export default function DocumentComparison({
    team,
    case: patientCase,
    availableStatusTransitions,
    document,
    ai,
    permissions,
}: Props) {
    const form = useForm({
        corrected_payload: ai.extraction?.payload ?? {},
        corrected_sections: ai.summary?.sections ?? {},
        reason: '',
    });
    const reprocessForm = useForm({});
    const statusForm = useForm({
        status: availableStatusTransitions[0]?.value ?? '',
        note: '',
    });

    const [payloadJson, setPayloadJson] = useState(
        JSON.stringify(ai.extraction?.payload ?? {}, null, 2),
    );
    const [sectionsJson, setSectionsJson] = useState(
        JSON.stringify(ai.summary?.sections ?? {}, null, 2),
    );
    const [jsonError, setJsonError] = useState<string | null>(null);

    const isProcessing = document.processing_in_progress;
    const isFailed = document.processing_status === 'failed';
    const isReady =
        document.processing_status === 'completed' ||
        document.processing_status === 'needs_review';
    const hasAiOutput = Boolean(
        ai.extracted_text ||
            ai.translation ||
            ai.extraction ||
            ai.summary,
    );

    const { start, stop } = usePoll(
        2500,
        {
            only: ['document', 'ai', 'permissions'],
        },
        {
            autoStart: false,
            keepAlive: true,
        },
    );

    useEffect(() => {
        if (isProcessing) {
            start();

            return;
        }

        stop();
    }, [isProcessing, start, stop]);

    useEffect(() => {
        statusForm.setData(
            'status',
            availableStatusTransitions[0]?.value ?? '',
        );
        // Only reset the selected next step when the case status options change.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [patientCase.status, availableStatusTransitions]);

    const statusTone = useMemo(() => {
        if (isFailed) {
            return 'border-rose-200 bg-rose-50 text-rose-700';
        }

        if (isProcessing) {
            return 'border-amber-200 bg-amber-50 text-amber-800';
        }

        if (document.processing_status === 'needs_review') {
            return 'border-sky-200 bg-sky-50 text-sky-700';
        }

        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }, [document.processing_status, isFailed, isProcessing]);

    return (
        <>
            <Head title={document.original_filename} />

            <div className="space-y-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-3">
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="rounded-full"
                        >
                            <Link
                                href={`/${team.slug}/cases/${patientCase.reference}`}
                            >
                                <ArrowLeft className="size-4" />
                                Back to case
                            </Link>
                        </Button>
                        <Heading
                            title={document.original_filename}
                            description={`AI document assist for case ${patientCase.reference}. Draft only — humans approve.`}
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Badge
                            className={cn(
                                'rounded-full border px-2.5 py-1',
                                statusTone,
                            )}
                        >
                            {isProcessing ? (
                                <LoaderCircle className="size-3.5 animate-spin" />
                            ) : isFailed ? (
                                <AlertTriangle className="size-3.5" />
                            ) : (
                                <CheckCircle2 className="size-3.5" />
                            )}
                            <span className="ms-1">
                                {document.processing_status_label}
                            </span>
                        </Badge>
                        <Badge className="rounded-full border border-slate-200 bg-white text-slate-600">
                            <FileText className="size-3.5" />
                            <span className="ms-1">{document.category}</span>
                        </Badge>
                        <Badge className="rounded-full border border-slate-200 bg-white text-slate-600">
                            <Bot className="size-3.5" />
                            <span className="ms-1">{ai.provider}</span>
                        </Badge>
                        {ai.extraction?.is_synthetic ||
                        ai.summary?.is_synthetic ? (
                            <Badge className="rounded-full border border-violet-200 bg-violet-50 text-violet-700">
                                <Sparkles className="size-3.5" />
                                <span className="ms-1">Demo AI output</span>
                            </Badge>
                        ) : null}
                        {permissions.canReprocess ? (
                            <Button
                                type="button"
                                variant="outline"
                                className="rounded-xl"
                                disabled={
                                    reprocessForm.processing || isProcessing
                                }
                                onClick={() => {
                                    reprocessForm.post(document.reprocess_url, {
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            router.reload({
                                                only: [
                                                    'document',
                                                    'ai',
                                                    'permissions',
                                                ],
                                            });
                                        },
                                    });
                                }}
                            >
                                <RefreshCw
                                    className={cn(
                                        'size-4',
                                        reprocessForm.processing &&
                                            'animate-spin',
                                    )}
                                />
                                Run AI again
                            </Button>
                        ) : null}
                        {permissions.canDownload ? (
                            <Button
                                asChild
                                variant="outline"
                                className="rounded-xl"
                            >
                                <a href={document.download_url}>
                                    <Download className="size-4" />
                                    Download
                                </a>
                            </Button>
                        ) : null}
                    </div>
                </div>

                <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <div className="flex items-start gap-2.5">
                        <ShieldAlert className="mt-0.5 size-4 shrink-0" />
                        <p>
                            AI helps staff process documents faster. It must
                            never diagnose, prescribe, or auto-approve clinical
                            content. Review every draft before acting on it.
                        </p>
                    </div>
                </div>

                {isProcessing ? (
                    <div className="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-4 text-sm text-sky-900">
                        <div className="flex items-start gap-2.5">
                            <LoaderCircle className="mt-0.5 size-4 shrink-0 animate-spin" />
                            <div className="min-w-0 flex-1 space-y-3">
                                <div>
                                    <p className="font-medium">
                                        AI assist is running in the background
                                    </p>
                                    <p className="mt-1 text-sky-800/90">
                                        Status updates automatically. Keep{' '}
                                        <code className="rounded bg-sky-100 px-1 py-0.5 text-xs">
                                            php artisan queue:work
                                        </code>{' '}
                                        running so extract → language →
                                        structure → translate → summary can
                                        finish.
                                    </p>
                                </div>
                                <ProcessingSteps
                                    steps={document.processing_steps}
                                />
                            </div>
                        </div>
                    </div>
                ) : null}

                {isFailed ? (
                    <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        <div className="flex items-start gap-2.5">
                            <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                            <div className="space-y-2">
                                <p>
                                    {document.processing_error ??
                                        'Processing failed for this document. You can still open the original file and continue the case manually.'}
                                </p>
                                {permissions.canReprocess ? (
                                    <p className="text-rose-700/90">
                                        Fix{' '}
                                        <code className="rounded bg-rose-100 px-1 py-0.5 text-xs">
                                            OPENAI_API_KEY
                                        </code>{' '}
                                        if needed, ensure the queue worker is
                                        running, then use Run AI again.
                                    </p>
                                ) : null}
                            </div>
                        </div>
                    </div>
                ) : null}

                <section className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_10px_30px_-24px_rgba(15,23,42,0.35)]">
                    <div className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                        <div className="flex items-center gap-2.5">
                            <span className="flex size-8 items-center justify-center rounded-xl bg-sky-50 text-sky-700">
                                <Activity className="size-4" />
                            </span>
                            <div>
                                <h2 className="text-sm font-semibold text-slate-900">
                                    Next case step
                                </h2>
                                <p className="text-xs text-slate-500">
                                    Review the AI draft here, then move the case
                                    forward without leaving this page.
                                </p>
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge className="rounded-full border border-sky-200 bg-sky-50 text-sky-700">
                                {patientCase.status_label}
                            </Badge>
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="rounded-full"
                            >
                                <Link href={patientCase.show_url}>
                                    Open full case
                                    <ArrowRight className="size-3.5" />
                                </Link>
                            </Button>
                        </div>
                    </div>

                    {permissions.canUpdateStatus ? (
                        <form
                            className="grid gap-4 bg-slate-50/60 p-5 md:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)_auto] md:items-end"
                            onSubmit={(event) => {
                                event.preventDefault();
                                statusForm.patch(
                                    patientCase.status_update_url,
                                    {
                                        preserveScroll: true,
                                    },
                                );
                            }}
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="next_status">Next status</Label>
                                <Select
                                    value={
                                        statusForm.data.status || undefined
                                    }
                                    onValueChange={(value) =>
                                        statusForm.setData('status', value)
                                    }
                                    disabled={
                                        availableStatusTransitions.length === 0
                                    }
                                >
                                    <SelectTrigger
                                        id="next_status"
                                        className="w-full bg-white"
                                    >
                                        <SelectValue placeholder="Select status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {availableStatusTransitions.map(
                                            (status) => (
                                                <SelectItem
                                                    key={status.value}
                                                    value={status.value}
                                                >
                                                    {status.label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={statusForm.errors.status}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="status_note">
                                    Note (optional)
                                </Label>
                                <Input
                                    id="status_note"
                                    className="bg-white"
                                    value={statusForm.data.note}
                                    onChange={(event) =>
                                        statusForm.setData(
                                            'note',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="e.g. Document reviewed — send to doctor"
                                />
                            </div>
                            <Button
                                type="submit"
                                className="rounded-xl bg-brand text-white hover:bg-brand/90"
                                disabled={
                                    statusForm.processing ||
                                    availableStatusTransitions.length === 0
                                }
                            >
                                Update status
                            </Button>
                        </form>
                    ) : (
                        <div className="px-5 py-4 text-sm text-slate-600">
                            You can review this document here. Status changes
                            need a coordinator or owner role.
                        </div>
                    )}
                </section>

                <div className="grid gap-5 xl:h-[calc(100svh-11rem)] xl:grid-cols-2 xl:items-stretch">
                    <section className="flex min-h-[28rem] flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_10px_30px_-24px_rgba(15,23,42,0.35)] xl:min-h-0">
                        <div className="flex shrink-0 items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                            <div className="flex items-center gap-2.5">
                                <span className="flex size-8 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                                    <FileText className="size-4" />
                                </span>
                                <div>
                                    <h2 className="text-sm font-semibold text-slate-900">
                                        Original document
                                    </h2>
                                    <p className="text-xs text-slate-500">
                                        Immutable source file · uploaded{' '}
                                        {new Date(
                                            document.uploaded_at,
                                        ).toLocaleString()}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div className="flex min-h-0 flex-1 flex-col bg-slate-50/80 p-4">
                            <div className="min-h-0 flex-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04),0_4px_12px_rgba(15,23,42,0.04)]">
                                {document.is_image ? (
                                    <div className="flex h-full items-center justify-center overflow-auto p-3">
                                        <img
                                            src={document.preview_url}
                                            alt={document.original_filename}
                                            className="max-h-full max-w-full object-contain"
                                        />
                                    </div>
                                ) : document.is_pdf ? (
                                    <iframe
                                        title={document.original_filename}
                                        src={document.preview_url}
                                        className="h-full w-full border-0"
                                    />
                                ) : (
                                    <div className="flex h-full flex-col items-center justify-center gap-3 p-8 text-center">
                                        <FileText className="size-8 text-slate-300" />
                                        <p className="text-sm text-slate-500">
                                            Preview unavailable for this file
                                            type. Download to inspect the
                                            original.
                                        </p>
                                    </div>
                                )}
                            </div>
                            <p className="mt-3 shrink-0 text-xs text-slate-500">
                                AI outputs never overwrite the stored source
                                file.
                            </p>
                        </div>
                    </section>

                    <section className="flex min-h-[28rem] flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_10px_30px_-24px_rgba(15,23,42,0.35)] xl:min-h-0">
                        <div className="flex shrink-0 items-center gap-2.5 border-b border-slate-100 px-5 py-4">
                            <span className="flex size-8 items-center justify-center rounded-xl bg-brand/10 text-brand">
                                <Bot className="size-4" />
                            </span>
                            <div>
                                <h2 className="text-sm font-semibold text-slate-900">
                                    AI document assist
                                </h2>
                                <p className="text-xs text-slate-500">
                                    Draft extraction, translation, and summary
                                    for staff review
                                </p>
                            </div>
                        </div>

                        <div className="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain bg-slate-50/60 p-4">
                            {!hasAiOutput && (isProcessing || !isReady) ? (
                                <EmptyAssistCard
                                    title="Waiting for AI assist"
                                    body="Once processing completes, extracted text, translation, structured fields, and a draft summary appear here."
                                />
                            ) : null}

                            <AssistPanel
                                icon={FileText}
                                title="Extracted text"
                                empty={!ai.extracted_text}
                                emptyLabel="No extracted text yet"
                            >
                                {ai.extracted_text ? (
                                    <pre className="max-h-72 overflow-y-auto whitespace-pre-wrap text-sm leading-6 text-slate-700">
                                        {ai.extracted_text}
                                    </pre>
                                ) : null}
                            </AssistPanel>

                            <AssistPanel
                                icon={Languages}
                                title="Translation"
                                hint={ai.disclaimer_translation}
                                empty={!ai.translation}
                                emptyLabel="No translation yet"
                            >
                                {ai.translation ? (
                                    <>
                                        <p className="mb-2 text-xs text-slate-500">
                                            {ai.translation.source_language} →{' '}
                                            {ai.translation.target_language} ·{' '}
                                            {ai.translation.provider}
                                        </p>
                                        <pre className="max-h-72 overflow-y-auto whitespace-pre-wrap text-sm leading-6 text-slate-700">
                                            {ai.translation.translated_text}
                                        </pre>
                                    </>
                                ) : null}
                            </AssistPanel>

                            <AssistPanel
                                icon={Sparkles}
                                title="Structured extraction"
                                hint={ai.disclaimer_summary}
                                empty={!ai.extraction}
                                emptyLabel="No structured extraction yet"
                            >
                                {ai.extraction ? (
                                    <pre className="max-h-72 overflow-auto rounded-xl border border-slate-200 bg-slate-50 p-3 font-mono text-xs text-slate-700">
                                        {JSON.stringify(
                                            ai.extraction.payload,
                                            null,
                                            2,
                                        )}
                                    </pre>
                                ) : null}
                            </AssistPanel>

                            <AssistPanel
                                icon={Bot}
                                title="AI summary"
                                hint={
                                    ai.summary?.disclaimer ||
                                    ai.disclaimer_summary
                                }
                                empty={!ai.summary}
                                emptyLabel="No summary yet"
                            >
                                {ai.summary ? (
                                    <pre className="max-h-72 overflow-auto rounded-xl border border-slate-200 bg-slate-50 p-3 font-mono text-xs text-slate-700">
                                        {JSON.stringify(
                                            ai.summary.sections,
                                            null,
                                            2,
                                        )}
                                    </pre>
                                ) : null}
                            </AssistPanel>
                        </div>
                    </section>
                </div>

                {permissions.canReviewAi ? (
                    <section className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_10px_30px_-24px_rgba(15,23,42,0.35)]">
                        <div className="border-b border-slate-100 px-5 py-4">
                            <div className="flex items-center gap-2.5">
                                <span className="flex size-8 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                                    <PencilLine className="size-4" />
                                </span>
                                <div>
                                    <h2 className="text-sm font-semibold text-slate-900">
                                        Human corrections
                                    </h2>
                                    <p className="text-xs text-slate-500">
                                        Corrections are versioned. Original AI
                                        payloads stay available.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <form
                            className="space-y-4 bg-slate-50/60 p-5"
                            onSubmit={(event) => {
                                event.preventDefault();
                                setJsonError(null);

                                try {
                                    const payload = JSON.parse(
                                        payloadJson,
                                    ) as Record<string, unknown>;
                                    const sections = JSON.parse(
                                        sectionsJson,
                                    ) as Record<string, unknown>;

                                    form.transform(() => ({
                                        corrected_payload: payload,
                                        corrected_sections: sections,
                                        reason: form.data.reason,
                                    }));
                                    form.patch(document.review_update_url, {
                                        preserveScroll: true,
                                    });
                                } catch {
                                    setJsonError(
                                        'Corrected payload and summary sections must be valid JSON.',
                                    );
                                }
                            }}
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="corrected_payload">
                                    Corrected structured payload (JSON)
                                </Label>
                                <Textarea
                                    id="corrected_payload"
                                    className="min-h-40 font-mono text-xs"
                                    value={payloadJson}
                                    onChange={(event) =>
                                        setPayloadJson(event.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="corrected_sections">
                                    Corrected summary sections (JSON)
                                </Label>
                                <Textarea
                                    id="corrected_sections"
                                    className="min-h-40 font-mono text-xs"
                                    value={sectionsJson}
                                    onChange={(event) =>
                                        setSectionsJson(event.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="reason">Reason</Label>
                                <Input
                                    id="reason"
                                    value={form.data.reason}
                                    onChange={(event) =>
                                        form.setData(
                                            'reason',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Why these corrections were needed"
                                />
                            </div>
                            <InputError
                                message={jsonError ?? form.errors.corrections}
                            />
                            <Button
                                type="submit"
                                className="rounded-xl bg-brand text-white hover:bg-brand/90"
                                disabled={form.processing}
                            >
                                <Save className="size-4" />
                                Save corrections
                            </Button>
                        </form>
                    </section>
                ) : null}

                {ai.review_events.length > 0 ? (
                    <section className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_10px_30px_-24px_rgba(15,23,42,0.35)]">
                        <div className="border-b border-slate-100 px-5 py-4">
                            <div className="flex items-center gap-2.5">
                                <span className="flex size-8 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                                    <History className="size-4" />
                                </span>
                                <div>
                                    <h2 className="text-sm font-semibold text-slate-900">
                                        Review history
                                    </h2>
                                    <p className="text-xs text-slate-500">
                                        Every human correction is recorded
                                    </p>
                                </div>
                            </div>
                        </div>
                        <ul className="divide-y divide-slate-100">
                            {ai.review_events.map((event) => (
                                <li
                                    key={event.id}
                                    className="px-5 py-3 text-sm text-slate-700"
                                >
                                    Corrected{' '}
                                    <span className="font-medium">
                                        {event.field_path}
                                    </span>
                                    {event.reason ? ` — ${event.reason}` : ''}
                                    <span className="text-slate-400">
                                        {' · '}
                                        {new Date(
                                            event.reviewed_at,
                                        ).toLocaleString()}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                ) : null}
            </div>
        </>
    );
}

function AssistPanel({
    icon: Icon,
    title,
    hint,
    empty,
    emptyLabel,
    children,
}: {
    icon: typeof FileText;
    title: string;
    hint?: string;
    empty?: boolean;
    emptyLabel?: string;
    children?: ReactNode;
}) {
    return (
        <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_10px_30px_-24px_rgba(15,23,42,0.35)]">
            <div className="flex items-center gap-2 border-b border-slate-100 px-4 py-3">
                <Icon className="size-4 text-slate-400" />
                <h3 className="text-sm font-semibold text-slate-900">{title}</h3>
            </div>
            <div className="space-y-2 p-4">
                {hint ? (
                    <p className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        {hint}
                    </p>
                ) : null}
                {empty ? (
                    <p className="text-sm text-slate-500">
                        {emptyLabel ?? 'Nothing available yet.'}
                    </p>
                ) : (
                    children
                )}
            </div>
        </div>
    );
}

function EmptyAssistCard({ title, body }: { title: string; body: string }) {
    return (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-5 text-center shadow-[0_10px_30px_-24px_rgba(15,23,42,0.35)]">
            <Bot className="mx-auto size-6 text-slate-300" />
            <p className="mt-2 text-sm font-medium text-slate-800">{title}</p>
            <p className="mt-1 text-xs text-slate-500">{body}</p>
        </div>
    );
}

function ProcessingSteps({ steps }: { steps: ProcessingStep[] }) {
    return (
        <ol className="grid gap-2 sm:grid-cols-2">
            {steps.map((step) => (
                <li
                    key={step.key}
                    className={cn(
                        'flex items-center gap-2 rounded-xl border px-3 py-2 text-xs',
                        step.state === 'complete' &&
                            'border-emerald-200 bg-emerald-50 text-emerald-800',
                        step.state === 'current' &&
                            'border-sky-300 bg-white text-sky-900 shadow-sm',
                        step.state === 'failed' &&
                            'border-rose-200 bg-rose-50 text-rose-800',
                        step.state === 'upcoming' &&
                            'border-sky-100 bg-sky-50/60 text-sky-700/70',
                    )}
                >
                    {step.state === 'complete' ? (
                        <CheckCircle2 className="size-3.5 shrink-0" />
                    ) : step.state === 'current' ? (
                        <LoaderCircle className="size-3.5 shrink-0 animate-spin" />
                    ) : step.state === 'failed' ? (
                        <AlertTriangle className="size-3.5 shrink-0" />
                    ) : (
                        <span className="size-3.5 shrink-0 rounded-full border border-current/30" />
                    )}
                    <span>{step.label}</span>
                </li>
            ))}
        </ol>
    );
}

DocumentComparison.layout = (props: {
    team: { slug: string };
    case: { reference: string; show_url?: string };
    document: { original_filename: string; uuid: string };
}) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard(props.team.slug) },
        { title: 'Cases', href: `/${props.team.slug}/cases` },
        {
            title: props.case.reference,
            href:
                props.case.show_url ??
                `/${props.team.slug}/cases/${props.case.reference}`,
        },
        {
            title: props.document.original_filename,
            href: `/${props.team.slug}/cases/${props.case.reference}/documents/${props.document.uuid}`,
        },
    ],
});
