import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Copy } from 'lucide-react';
import { useState } from 'react';
import { intakeText, isRtl } from '@/lib/intake-i18n';

type Props = {
    organization: {
        name: string;
        slug: string;
        logo_url?: string | null;
    };
    locale?: string;
    reference: string;
    trackingUrl: string;
};

export default function IntakeConfirmation({
    organization,
    locale = 'en',
    reference,
    trackingUrl,
}: Props) {
    const t = (key: string) => intakeText(locale, key);
    const [copied, setCopied] = useState<'reference' | 'link' | null>(null);

    const copy = async (value: string, kind: 'reference' | 'link') => {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(kind);
            window.setTimeout(() => setCopied(null), 2000);
        } catch {
            // Clipboard may be unavailable; the values stay visible on screen.
        }
    };

    return (
        <div
            className="flex min-h-screen items-center justify-center bg-[#eceee8] px-4 py-8"
            dir={isRtl(locale) ? 'rtl' : 'ltr'}
        >
            <Head title={t('confTitle')} />
            <div className="w-full max-w-lg space-y-6 rounded-[24px] border border-slate-200/80 bg-white p-6 shadow-[0_18px_50px_-36px_rgba(15,23,42,0.35)] sm:p-8">
                <div className="space-y-3">
                    {organization.logo_url ? (
                        <img
                            src={organization.logo_url}
                            alt=""
                            className="h-10 max-w-[160px] object-contain"
                        />
                    ) : null}
                    <p className="text-sm font-medium text-brand">
                        {organization.name}
                    </p>
                    <div className="flex items-center gap-3">
                        <CheckCircle2
                            className="size-8 shrink-0 text-emerald-600"
                            aria-hidden="true"
                        />
                        <h1 className="font-display text-2xl font-semibold text-slate-900">
                            {t('confTitle')}
                        </h1>
                    </div>
                    <p className="text-slate-600">{t('confIntro')}</p>
                </div>

                <div className="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p className="text-xs font-medium text-slate-500">
                        {t('reference')}
                    </p>
                    <div className="flex items-center justify-between gap-3">
                        <span className="font-mono text-lg font-semibold text-slate-900">
                            {reference}
                        </span>
                        <button
                            type="button"
                            onClick={() => copy(reference, 'reference')}
                            className="inline-flex min-h-11 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 hover:bg-slate-100"
                        >
                            <Copy className="size-4" aria-hidden="true" />
                            {copied === 'reference' ? t('copied') : t('copy')}
                        </button>
                    </div>
                    <p className="text-xs text-slate-500">{t('confKeepRef')}</p>
                </div>

                <div className="space-y-3">
                    <Link
                        href={trackingUrl}
                        className="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-brand px-4 py-2.5 text-sm font-medium text-brand-foreground hover:opacity-95"
                    >
                        {t('openTracking')}
                    </Link>
                    <button
                        type="button"
                        onClick={() =>
                            copy(
                                new URL(trackingUrl, window.location.origin)
                                    .href,
                                'link',
                            )
                        }
                        className="inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-xl border border-slate-200 px-4 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        <Copy className="size-4" aria-hidden="true" />
                        {copied === 'link' ? t('copied') : t('copyLink')}
                    </button>
                    <p className="text-xs text-slate-500">{t('saveLink')}</p>
                </div>

                <div className="space-y-3 border-t border-slate-100 pt-5">
                    <h2 className="text-sm font-semibold text-slate-900">
                        {t('nextTitle')}
                    </h2>
                    <ol className="space-y-2 text-sm text-slate-600">
                        {['next1', 'next2', 'next3'].map((key, index) => (
                            <li key={key} className="flex items-start gap-3">
                                <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand/10 text-xs font-semibold text-brand">
                                    {index + 1}
                                </span>
                                <span className="pt-0.5">{t(key)}</span>
                            </li>
                        ))}
                    </ol>
                </div>
            </div>
        </div>
    );
}
