import { Head, useForm } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';
import { intakeText, isRtl } from '@/lib/intake-i18n';
import { cn } from '@/lib/utils';

type Props = {
    organization: {
        name: string;
        slug: string;
        logo_url?: string | null;
    };
    case: {
        reference: string;
        status: string;
        state: string;
        stage: number | null;
        department: string | null;
        patient_first_name: string;
        preferred_language: string;
        last_activity_at: string | null;
    };
    messages?: Array<{
        id: string;
        subject: string | null;
        body: string;
        sent_at: string | null;
        from_patient: boolean;
    }>;
    appointments?: Array<{
        id: string;
        type: string;
        starts_at: string;
        ends_at: string | null;
        department: string | null;
        location: string | null;
        instructions: string | null;
    }>;
    timezone?: string;
    notice?: 'documents' | 'message' | null;
    canRespond?: boolean;
    canReply?: boolean;
};

const STAGES = [1, 2, 3, 4, 5, 6];

function formatWhen(
    value: string,
    locale: string,
    timeZone: string,
    withTime = true,
): string {
    try {
        return new Intl.DateTimeFormat(locale, {
            dateStyle: 'full',
            ...(withTime ? { timeStyle: 'short' as const } : {}),
            timeZone,
        }).format(new Date(value));
    } catch {
        return new Date(value).toLocaleString();
    }
}

export default function CaseTracking({
    organization,
    case: patientCase,
    messages = [],
    appointments = [],
    timezone = 'UTC',
    notice = null,
    canRespond = false,
    canReply = false,
}: Props) {
    const [token] = useState(() =>
        typeof window === 'undefined'
            ? ''
            : (new URLSearchParams(window.location.search).get('token') ?? ''),
    );
    const casePath = `/p/${organization.slug}/cases/${patientCase.reference}`;
    const uploadForm = useForm<{
        token: string;
        documents: File[];
        document_categories: string[];
    }>({ token, documents: [], document_categories: [] });
    const replyForm = useForm({ token, body: '' });
    const locale = ['en', 'tr', 'ar', 'ru', 'az'].includes(
        patientCase.preferred_language,
    )
        ? patientCase.preferred_language
        : 'en';
    const t = (key: string) => intakeText(locale, key);
    const stage = patientCase.stage;

    return (
        <div
            className="flex min-h-screen items-center justify-center bg-[#eceee8] px-4 py-8"
            dir={isRtl(locale) ? 'rtl' : 'ltr'}
        >
            <Head title={`${t('reference')} ${patientCase.reference}`} />
            <div className="w-full max-w-lg space-y-6 rounded-[24px] border border-slate-200/80 bg-white p-6 shadow-[0_18px_50px_-36px_rgba(15,23,42,0.35)] sm:p-8">
                <div className="space-y-1">
                    {organization.logo_url ? (
                        <img
                            src={organization.logo_url}
                            alt=""
                            className="mb-2 h-10 max-w-[160px] object-contain"
                        />
                    ) : null}
                    <p className="text-sm font-medium text-brand">
                        {organization.name}
                    </p>
                    <h1 className="font-display text-2xl font-semibold text-slate-900">
                        {t('hello')} {patientCase.patient_first_name}
                    </h1>
                </div>

                <dl className="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt className="text-slate-500">{t('reference')}</dt>
                        <dd className="font-mono font-medium text-slate-900">
                            {patientCase.reference}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-slate-500">{t('status')}</dt>
                        <dd className="font-medium text-slate-900">
                            {patientCase.status}
                        </dd>
                    </div>
                    {patientCase.department ? (
                        <div className="col-span-2">
                            <dt className="text-slate-500">
                                {t('department')}
                            </dt>
                            <dd className="font-medium text-slate-900">
                                {patientCase.department}
                            </dd>
                        </div>
                    ) : null}
                </dl>

                {notice ? (
                    <p
                        role="status"
                        className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                    >
                        {notice === 'documents'
                            ? t('filesSent')
                            : t('replySent')}
                    </p>
                ) : null}

                {patientCase.state === 'awaiting_documents' ? (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <h2 className="text-sm font-semibold text-amber-900">
                            {t('needFromYou')}
                        </h2>
                        <p className="mt-1 text-sm text-amber-900">
                            {t('state_awaiting_documents')}
                        </p>
                    </div>
                ) : null}

                <div className="rounded-xl border border-brand/20 bg-brand/5 p-4">
                    <h2 className="text-sm font-semibold text-slate-900">
                        {t('whatThisMeans')}
                    </h2>
                    <p className="mt-1 text-sm text-slate-700">
                        {t(`state_${patientCase.state}`)}
                    </p>
                </div>

                {appointments.length > 0 ? (
                    <section className="space-y-3">
                        <h2 className="text-sm font-semibold text-slate-900">
                            {t('appointmentTitle')}
                        </h2>
                        {appointments.map((appointment) => (
                            <div
                                key={appointment.id}
                                className="space-y-2 rounded-xl border border-slate-200 p-4 text-sm"
                            >
                                <p className="font-semibold text-slate-900">
                                    {appointment.type}
                                    {appointment.department
                                        ? ` · ${appointment.department}`
                                        : ''}
                                </p>
                                <dl className="space-y-1.5">
                                    <div>
                                        <dt className="text-xs text-slate-500">
                                            {t('whenLabel')}
                                        </dt>
                                        <dd className="text-slate-800">
                                            {formatWhen(
                                                appointment.starts_at,
                                                locale,
                                                timezone,
                                            )}{' '}
                                            <span className="text-xs text-slate-500">
                                                ({timezone})
                                            </span>
                                        </dd>
                                    </div>
                                    {appointment.location ? (
                                        <div>
                                            <dt className="text-xs text-slate-500">
                                                {t('whereLabel')}
                                            </dt>
                                            <dd className="text-slate-800">
                                                {appointment.location}
                                            </dd>
                                        </div>
                                    ) : null}
                                    {appointment.instructions ? (
                                        <div>
                                            <dt className="text-xs text-slate-500">
                                                {t('instructionsLabel')}
                                            </dt>
                                            <dd className="whitespace-pre-line text-slate-800">
                                                {appointment.instructions}
                                            </dd>
                                        </div>
                                    ) : null}
                                </dl>
                            </div>
                        ))}
                    </section>
                ) : null}

                {canRespond ? (
                    <section className="space-y-3">
                        <h2 className="text-sm font-semibold text-slate-900">
                            {t('uploadTitle')}
                        </h2>
                        <form
                            className="space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                uploadForm.post(`${casePath}/documents`, {
                                    forceFormData: true,
                                    preserveScroll: true,
                                    onSuccess: () => uploadForm.reset(),
                                });
                            }}
                        >
                            <label className="flex min-h-11 cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-center text-sm hover:border-blue-300">
                                <span className="font-medium text-slate-700">
                                    {t('chooseFiles')}
                                </span>
                                <span className="text-xs text-slate-500">
                                    {t('uploadHint')}
                                </span>
                                <input
                                    type="file"
                                    multiple
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    className="sr-only"
                                    onChange={(event) => {
                                        const files = Array.from(
                                            event.target.files ?? [],
                                        );
                                        uploadForm.setData({
                                            ...uploadForm.data,
                                            documents: files,
                                            document_categories: files.map(
                                                () =>
                                                    'other_supporting_document',
                                            ),
                                        });
                                    }}
                                />
                            </label>
                            {uploadForm.data.documents.length > 0 ? (
                                <ul className="space-y-1 text-xs text-slate-600">
                                    {uploadForm.data.documents.map(
                                        (file, index) => (
                                            <li
                                                key={`${file.name}-${index}`}
                                                className="truncate"
                                            >
                                                {file.name}
                                            </li>
                                        ),
                                    )}
                                </ul>
                            ) : null}
                            {Object.values(uploadForm.errors).map((error) => (
                                <p
                                    key={error}
                                    className="text-xs text-red-600"
                                    role="alert"
                                >
                                    {error}
                                </p>
                            ))}
                            {uploadForm.processing && uploadForm.progress ? (
                                <div
                                    role="progressbar"
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                    aria-valuenow={
                                        uploadForm.progress.percentage ?? 0
                                    }
                                    className="h-2 overflow-hidden rounded-full bg-slate-200"
                                >
                                    <div
                                        className="h-full rounded-full bg-brand transition-all"
                                        style={{
                                            width: `${uploadForm.progress.percentage ?? 0}%`,
                                        }}
                                    />
                                </div>
                            ) : null}
                            <button
                                type="submit"
                                disabled={
                                    uploadForm.processing ||
                                    uploadForm.data.documents.length === 0
                                }
                                className="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-brand px-4 text-sm font-medium text-brand-foreground disabled:opacity-50"
                            >
                                {uploadForm.processing
                                    ? `${t('uploading')} ${uploadForm.progress?.percentage ?? 0}%`
                                    : t('sendFiles')}
                            </button>
                        </form>
                    </section>
                ) : null}

                {messages.length > 0 || canReply ? (
                    <section className="space-y-3">
                        <h2 className="text-sm font-semibold text-slate-900">
                            {t('messagesTitle')}
                        </h2>
                        {canReply ? (
                            <form
                                className="space-y-2"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    replyForm.post(`${casePath}/messages`, {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            replyForm.setData('body', ''),
                                    });
                                }}
                            >
                                <label
                                    htmlFor="portal-reply"
                                    className="text-sm font-medium text-slate-800"
                                >
                                    {t('replyTitle')}
                                </label>
                                <textarea
                                    id="portal-reply"
                                    value={replyForm.data.body}
                                    onChange={(event) =>
                                        replyForm.setData(
                                            'body',
                                            event.target.value,
                                        )
                                    }
                                    rows={3}
                                    maxLength={3000}
                                    placeholder={t('replyHint')}
                                    className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-200"
                                />
                                {replyForm.errors.body ? (
                                    <p
                                        className="text-xs text-red-600"
                                        role="alert"
                                    >
                                        {replyForm.errors.body}
                                    </p>
                                ) : null}
                                <button
                                    type="submit"
                                    disabled={
                                        replyForm.processing ||
                                        replyForm.data.body.trim().length < 2
                                    }
                                    className="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-brand px-4 text-sm font-medium text-brand-foreground disabled:opacity-50"
                                >
                                    {replyForm.processing
                                        ? t('sending')
                                        : t('sendReply')}
                                </button>
                            </form>
                        ) : null}
                        {messages.map((message) => (
                            <article
                                key={message.id}
                                className={cn(
                                    'space-y-1 rounded-xl border p-4 text-sm',
                                    message.from_patient
                                        ? 'border-blue-200 bg-blue-50/60'
                                        : 'border-slate-200',
                                )}
                            >
                                <p className="text-xs font-medium text-slate-500">
                                    {message.from_patient
                                        ? t('fromYou')
                                        : t('fromHospital')}
                                </p>
                                {message.subject ? (
                                    <h3 className="font-semibold text-slate-900">
                                        {message.subject}
                                    </h3>
                                ) : null}
                                <p className="whitespace-pre-line text-slate-700">
                                    {message.body}
                                </p>
                                {message.sent_at ? (
                                    <p className="text-xs text-slate-500">
                                        {formatWhen(
                                            message.sent_at,
                                            locale,
                                            timezone,
                                        )}
                                    </p>
                                ) : null}
                            </article>
                        ))}
                    </section>
                ) : null}

                {stage !== null ? (
                    <ol
                        className="space-y-0"
                        aria-label={t('whatThisMeans')}
                    >
                        {STAGES.map((number) => {
                            const done = number < stage;
                            const current = number === stage;

                            return (
                                <li
                                    key={number}
                                    className="flex items-start gap-3"
                                    aria-current={current ? 'step' : undefined}
                                >
                                    <div className="flex flex-col items-center">
                                        <span
                                            className={cn(
                                                'flex size-7 items-center justify-center rounded-full border text-xs font-semibold',
                                                done &&
                                                    'border-emerald-600 bg-emerald-600 text-white',
                                                current &&
                                                    'border-brand bg-brand text-brand-foreground',
                                                !done &&
                                                    !current &&
                                                    'border-slate-300 bg-white text-slate-400',
                                            )}
                                        >
                                            {done ? (
                                                <Check
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            ) : (
                                                number
                                            )}
                                        </span>
                                        {number < STAGES.length ? (
                                            <span
                                                className={cn(
                                                    'h-6 w-px',
                                                    done
                                                        ? 'bg-emerald-600'
                                                        : 'bg-slate-200',
                                                )}
                                            />
                                        ) : null}
                                    </div>
                                    <span
                                        className={cn(
                                            'pt-1 text-sm',
                                            current
                                                ? 'font-semibold text-slate-900'
                                                : done
                                                  ? 'text-slate-700'
                                                  : 'text-slate-400',
                                        )}
                                    >
                                        {t(`journey${number}`)}
                                    </span>
                                </li>
                            );
                        })}
                    </ol>
                ) : null}

                <p className="text-xs text-slate-500">{t('trackNote')}</p>
            </div>
        </div>
    );
}
