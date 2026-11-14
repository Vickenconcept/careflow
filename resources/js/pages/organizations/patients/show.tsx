import { Head, Link, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    Check,
    FileText,
    Globe2,
    Languages,
    Mail,
    MessageSquare,
    Pencil,
    Phone,
    ShieldCheck,
    UserRound,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { AppSelect } from '@/components/app-select';
import { FormSheet } from '@/components/case/form-sheet';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type Option = { value: string; label: string };

type Props = {
    team: { id: number; name: string; slug: string };
    patient: {
        id: string;
        first_name: string;
        last_name: string;
        full_name: string;
        email: string;
        phone: string | null;
        date_of_birth: string | null;
        gender: string | null;
        gender_label: string | null;
        country_of_residence: string | null;
        preferred_language: string;
        is_demo: boolean;
        created_at: string | null;
    };
    cases: Array<{
        reference: string;
        status_label: string;
        department: string | null;
        coordinator: string | null;
        documents_count: number;
        last_activity_at: string | null;
        url: string;
    }>;
    consents: Array<{
        id: number;
        label: string;
        accepted: boolean;
        consented_at: string | null;
    }>;
    messagesUrl: string;
    updateUrl: string;
    canEdit: boolean;
    options: { genders: Option[]; languages: Option[] };
};

function Section({
    title,
    icon: Icon,
    action,
    children,
}: {
    title: string;
    icon: typeof UserRound;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="light-surface rounded-2xl border border-slate-200/80 bg-white shadow-[0_12px_40px_-28px_rgba(15,23,42,0.35)]">
            <div className="flex items-center gap-2.5 border-b border-slate-100 px-5 py-3.5">
                <span className="flex size-7 items-center justify-center rounded-lg bg-brand/10 text-brand">
                    <Icon className="size-3.5" />
                </span>
                <h2 className="flex-1 text-sm font-semibold text-slate-900">
                    {title}
                </h2>
                {action}
            </div>
            <div className="p-5">{children}</div>
        </section>
    );
}

function Detail({
    label,
    value,
    icon: Icon,
}: {
    label: string;
    value: string | null | undefined;
    icon: typeof Mail;
}) {
    return (
        <div className="min-w-0">
            <dt className="flex items-center gap-1.5 text-xs font-medium text-slate-500">
                <Icon className="size-3.5" />
                {label}
            </dt>
            <dd className="mt-1 text-sm font-medium break-words text-slate-900">
                {value || '—'}
            </dd>
        </div>
    );
}

export default function PatientShow({
    patient,
    cases,
    consents,
    messagesUrl,
    updateUrl,
    canEdit,
    options,
}: Props) {
    const [editing, setEditing] = useState(false);
    const form = useForm({
        first_name: patient.first_name,
        last_name: patient.last_name,
        email: patient.email,
        phone: patient.phone ?? '',
        date_of_birth: patient.date_of_birth ?? '',
        gender: patient.gender ?? '',
        country_of_residence: patient.country_of_residence ?? '',
        preferred_language: patient.preferred_language,
    });

    return (
        <>
            <Head title={patient.full_name} />
            <div className="space-y-5 p-4 md:p-6">
                <section className="light-surface flex flex-wrap items-center gap-4 rounded-2xl border border-slate-200/80 bg-white px-5 py-5 shadow-[0_12px_40px_-28px_rgba(15,23,42,0.35)]">
                    <span className="flex size-12 items-center justify-center rounded-full bg-brand/10 text-base font-semibold text-brand">
                        {(patient.first_name[0] ?? '') +
                            (patient.last_name[0] ?? '')}
                    </span>
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-xl font-semibold tracking-tight text-slate-900">
                                {patient.full_name}
                            </h1>
                            {patient.is_demo ? (
                                <span className="rounded-full border border-slate-300 px-2.5 py-0.5 text-xs font-semibold text-slate-600">
                                    Demo
                                </span>
                            ) : null}
                        </div>
                        <p className="mt-0.5 text-sm text-slate-500">
                            {cases.length}{' '}
                            {cases.length === 1 ? 'case' : 'cases'}
                            {patient.created_at
                                ? ` · patient since ${new Date(patient.created_at).toLocaleDateString([], { dateStyle: 'medium' })}`
                                : ''}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            asChild
                            variant="outline"
                            className="rounded-full"
                        >
                            <Link href={messagesUrl}>
                                <MessageSquare className="size-4" />
                                Message patient
                            </Link>
                        </Button>
                        {canEdit ? (
                            <Button
                                type="button"
                                className="rounded-full bg-brand text-white hover:bg-brand/90"
                                onClick={() => setEditing(true)}
                            >
                                <Pencil className="size-4" />
                                Edit details
                            </Button>
                        ) : null}
                    </div>
                </section>

                <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
                    <div className="space-y-5">
                        <Section title="Contact and details" icon={UserRound}>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Detail
                                    label="Email"
                                    value={patient.email}
                                    icon={Mail}
                                />
                                <Detail
                                    label="Phone"
                                    value={patient.phone}
                                    icon={Phone}
                                />
                                <Detail
                                    label="Date of birth"
                                    value={
                                        patient.date_of_birth
                                            ? new Date(
                                                  patient.date_of_birth,
                                              ).toLocaleDateString([], {
                                                  dateStyle: 'medium',
                                              })
                                            : null
                                    }
                                    icon={CalendarDays}
                                />
                                <Detail
                                    label="Gender"
                                    value={patient.gender_label}
                                    icon={UserRound}
                                />
                                <Detail
                                    label="Country"
                                    value={patient.country_of_residence}
                                    icon={Globe2}
                                />
                                <Detail
                                    label="Language"
                                    value={patient.preferred_language.toUpperCase()}
                                    icon={Languages}
                                />
                            </dl>
                        </Section>

                        <Section title="Consents" icon={ShieldCheck}>
                            {consents.length === 0 ? (
                                <p className="text-sm text-slate-500">
                                    No consents recorded.
                                </p>
                            ) : (
                                <ul className="space-y-2">
                                    {consents.map((consent) => (
                                        <li
                                            key={consent.id}
                                            className="flex items-center gap-2.5 text-sm"
                                        >
                                            <span
                                                className={
                                                    consent.accepted
                                                        ? 'flex size-5 items-center justify-center rounded-full bg-brand/10 text-brand'
                                                        : 'flex size-5 items-center justify-center rounded-full bg-slate-100 text-slate-500'
                                                }
                                            >
                                                {consent.accepted ? (
                                                    <Check className="size-3" />
                                                ) : (
                                                    <X className="size-3" />
                                                )}
                                            </span>
                                            <span className="flex-1 text-slate-800">
                                                {consent.label}
                                            </span>
                                            <span className="text-xs text-slate-500">
                                                {consent.consented_at
                                                    ? new Date(
                                                          consent.consented_at,
                                                      ).toLocaleDateString([], {
                                                          dateStyle: 'medium',
                                                      })
                                                    : ''}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Section>
                    </div>

                    <Section title="Cases" icon={FileText}>
                        <ul className="divide-y divide-slate-100 rounded-xl border border-slate-200">
                            {cases.map((item) => (
                                <li key={item.reference}>
                                    <Link
                                        href={item.url}
                                        className="flex flex-wrap items-center gap-3 px-4 py-3 text-sm hover:bg-slate-50"
                                    >
                                        <span className="font-mono text-xs font-semibold text-brand">
                                            {item.reference}
                                        </span>
                                        <span className="rounded-full bg-brand/10 px-2 py-0.5 text-xs font-medium text-brand">
                                            {item.status_label}
                                        </span>
                                        <span className="min-w-0 flex-1 truncate text-slate-600">
                                            {[
                                                item.department,
                                                item.coordinator
                                                    ? `Coordinator: ${item.coordinator}`
                                                    : null,
                                                `${item.documents_count} documents`,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </span>
                                        <span className="text-xs text-slate-500">
                                            {item.last_activity_at
                                                ? new Date(
                                                      item.last_activity_at,
                                                  ).toLocaleDateString([], {
                                                      dateStyle: 'medium',
                                                  })
                                                : ''}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Section>
                </div>
            </div>

            {canEdit ? (
                <FormSheet
                    open={editing}
                    onOpenChange={setEditing}
                    title="Edit patient details"
                    description="Changes apply to all of this patient's cases. Future emails go to the new address."
                >
                    <form
                        className="space-y-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.transform((data) => ({
                                ...data,
                                phone: data.phone || null,
                                date_of_birth: data.date_of_birth || null,
                                gender: data.gender || null,
                                country_of_residence:
                                    data.country_of_residence
                                        .trim()
                                        .toUpperCase() || null,
                            }));
                            form.patch(updateUrl, {
                                preserveScroll: true,
                                onSuccess: () => setEditing(false),
                            });
                        }}
                    >
                        <div className="grid gap-3 sm:grid-cols-2">
                            {(
                                [
                                    ['first_name', 'First name', 'text'],
                                    ['last_name', 'Last name', 'text'],
                                    ['email', 'Email', 'email'],
                                    ['phone', 'Phone', 'text'],
                                    ['date_of_birth', 'Date of birth', 'date'],
                                    [
                                        'country_of_residence',
                                        'Country code',
                                        'text',
                                    ],
                                ] as const
                            ).map(([key, label, type]) => (
                                <div key={key} className="grid gap-1.5">
                                    <Label className="text-sm font-medium text-slate-700">
                                        {label}
                                    </Label>
                                    <Input
                                        type={type}
                                        maxLength={
                                            key === 'country_of_residence'
                                                ? 2
                                                : undefined
                                        }
                                        value={form.data[key]}
                                        onChange={(event) =>
                                            form.setData(
                                                key,
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={form.errors[key]} />
                                </div>
                            ))}
                            <div className="grid gap-1.5">
                                <Label className="text-sm font-medium text-slate-700">
                                    Gender
                                </Label>
                                <AppSelect
                                    value={form.data.gender}
                                    onChange={(value) =>
                                        form.setData('gender', value)
                                    }
                                    options={options.genders}
                                    emptyLabel="Not recorded"
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label className="text-sm font-medium text-slate-700">
                                    Preferred language
                                </Label>
                                <AppSelect
                                    value={form.data.preferred_language}
                                    onChange={(value) =>
                                        form.setData(
                                            'preferred_language',
                                            value,
                                        )
                                    }
                                    options={options.languages}
                                />
                            </div>
                        </div>
                        <Button
                            type="submit"
                            className="h-11 rounded-full bg-brand text-white hover:bg-brand/90"
                            disabled={form.processing}
                        >
                            Save details
                        </Button>
                    </form>
                </FormSheet>
            ) : null}
        </>
    );
}

PatientShow.layout = (props: {
    team: { slug: string };
    patient: { id: string; full_name: string };
}) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard(props.team.slug) },
        { title: 'Patients', href: `/${props.team.slug}/patients` },
        {
            title: props.patient.full_name,
            href: `/${props.team.slug}/patients/${props.patient.id}`,
        },
    ],
});
