import { Head, Link } from '@inertiajs/react';

type Props = {
    organization: {
        name: string;
        slug: string;
    };
    reference: string;
    trackingUrl: string;
};

export default function IntakeConfirmation({
    organization,
    reference,
    trackingUrl,
}: Props) {
    return (
        <div className="flex min-h-screen items-center justify-center bg-gradient-to-b from-slate-50 via-white to-blue-50/50 px-4">
            <Head title="Enquiry submitted" />
            <div className="w-full max-w-lg space-y-4 rounded-[24px] border border-slate-200/80 bg-white p-8 shadow-[0_18px_50px_-36px_rgba(15,23,42,0.35)]">
                <p className="text-sm font-medium text-brand">
                    {organization.name}
                </p>
                <h1 className="font-display text-2xl font-semibold text-slate-900">
                    Enquiry received
                </h1>
                <p className="text-slate-600">
                    Your case reference is{' '}
                    <span className="font-semibold text-slate-900">
                        {reference}
                    </span>
                    . Keep this reference and your private tracking link.
                </p>
                <Link
                    href={trackingUrl}
                    className="inline-flex rounded-xl bg-brand px-4 py-2.5 text-sm font-medium text-brand-foreground hover:opacity-95"
                >
                    Open secure tracking page
                </Link>
            </div>
        </div>
    );
}
