import { Head } from '@inertiajs/react';

type Props = {
    organization: {
        name: string;
        slug: string;
    };
    case: {
        reference: string;
        status: string;
        department: string | null;
        patient_first_name: string;
        preferred_language: string;
        last_activity_at: string | null;
    };
};

export default function CaseTracking({
    organization,
    case: patientCase,
}: Props) {
    return (
        <div className="flex min-h-screen items-center justify-center bg-gradient-to-b from-slate-50 via-white to-blue-50/50 px-4">
            <Head title={`Case ${patientCase.reference}`} />
            <div className="w-full max-w-lg space-y-4 rounded-[24px] border border-slate-200/80 bg-white p-8 shadow-[0_18px_50px_-36px_rgba(15,23,42,0.35)]">
                <p className="text-sm font-medium text-brand">
                    {organization.name}
                </p>
                <h1 className="font-display text-2xl font-semibold text-slate-900">
                    Hello {patientCase.patient_first_name}
                </h1>
                <dl className="space-y-3 text-sm">
                    <div>
                        <dt className="text-slate-500">Case reference</dt>
                        <dd className="font-medium text-slate-900">
                            {patientCase.reference}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-slate-500">Status</dt>
                        <dd className="font-medium text-slate-900">
                            {patientCase.status}
                        </dd>
                    </div>
                    {patientCase.department ? (
                        <div>
                            <dt className="text-slate-500">Department</dt>
                            <dd className="font-medium text-slate-900">
                                {patientCase.department}
                            </dd>
                        </div>
                    ) : null}
                </dl>
                <p className="text-xs text-slate-500">
                    This page only shows administrative status approved for
                    patient access. Clinical notes and AI processing details are
                    never shown here.
                </p>
            </div>
        </div>
    );
}
