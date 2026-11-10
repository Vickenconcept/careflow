import { Head, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    Check,
    FileText,
    Languages,
    Mail,
    MapPin,
    Phone,
    ShieldCheck,
    Stethoscope,
    UserRound,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import InputError from '@/components/input-error';
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
import { intakeText, isRtl, type IntakeLocale } from '@/lib/intake-i18n';
import { cn } from '@/lib/utils';

const NONE_VALUE = '__none__';

type Option = { value: string; label: string };
type DepartmentOption = { id: number; name: string; slug: string };

type Props = {
    organization: {
        name: string;
        slug: string;
        brand_primary_color: string | null;
        default_language: string;
        supported_languages: string[];
    };
    departments: DepartmentOption[];
    genderOptions: Option[];
    documentCategories: Option[];
    consentVersions: Record<string, string>;
    initialLanguage: string;
};

const CAREFlow_BLUE = '#2f6fed';

const STEP_FIELDS = [
    [
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'country_of_residence',
        'email',
        'phone',
        'preferred_language',
    ],
    [
        'department_id',
        'reason_for_contact',
        'diagnosis',
        'symptoms_or_history',
        'previous_procedures',
        'current_medications',
        'known_allergies',
        'additional_comments',
    ],
    ['documents', 'document_categories'],
    ['enquiry_consent', 'health_data_consent', 'ai_processing_consent'],
] as const;

export default function PortalIntake({
    organization,
    departments,
    genderOptions,
    documentCategories,
    consentVersions,
    initialLanguage,
}: Props) {
    const [step, setStep] = useState(0);
    const [maxReachedStep, setMaxReachedStep] = useState(0);
    const [localErrors, setLocalErrors] = useState<Record<string, string>>({});
    const [locale, setLocale] = useState<IntakeLocale>(
        (organization.supported_languages.includes(initialLanguage)
            ? initialLanguage
            : 'en') as IntakeLocale,
    );

    const form = useForm({
        first_name: '',
        last_name: '',
        date_of_birth: '',
        gender: '',
        country_of_residence: '',
        email: '',
        phone: '',
        preferred_language: locale,
        department_id: '',
        reason_for_contact: '',
        diagnosis: '',
        symptoms_or_history: '',
        previous_procedures: '',
        current_medications: '',
        known_allergies: '',
        additional_comments: '',
        documents: [] as File[],
        document_categories: [] as string[],
        enquiry_consent: false,
        health_data_consent: false,
        ai_processing_consent: false,
    });

    const t = (key: string) => intakeText(locale, key);
    const rtl = isRtl(locale);
    const accent = organization.brand_primary_color || CAREFlow_BLUE;

    const steps = useMemo(
        () => [
            { label: t('stepPersonal'), icon: UserRound },
            { label: t('stepMedical'), icon: Stethoscope },
            { label: t('stepDocuments'), icon: FileText },
            { label: t('stepConsent'), icon: ShieldCheck },
        ],
        [locale],
    );

    const allErrors = { ...localErrors, ...form.errors };

    useEffect(() => {
        if (Object.keys(form.errors).length === 0) {
            return;
        }

        const errorStep = STEP_FIELDS.findIndex((fields) =>
            fields.some((field) =>
                Object.keys(form.errors).some(
                    (key) => key === field || key.startsWith(`${field}.`),
                ),
            ),
        );

        if (errorStep >= 0) {
            setStep(errorStep);
            setMaxReachedStep((reached) => Math.max(reached, errorStep));
        }
    }, [form.errors]);

    const validateStep = (currentStep: number): boolean => {
        const nextErrors: Record<string, string> = {};

        if (currentStep === 0) {
            if (!form.data.first_name.trim()) {
                nextErrors.first_name = 'First name is required.';
            }
            if (!form.data.last_name.trim()) {
                nextErrors.last_name = 'Last name is required.';
            }
            if (!form.data.email.trim()) {
                nextErrors.email = 'Email is required.';
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.data.email)) {
                nextErrors.email = 'Enter a valid email address.';
            }
        }

        if (currentStep === 1) {
            if (!form.data.reason_for_contact.trim()) {
                nextErrors.reason_for_contact =
                    'Please tell us why you are contacting the hospital.';
            }
        }

        if (currentStep === 3) {
            if (!form.data.enquiry_consent) {
                nextErrors.enquiry_consent =
                    'You must accept the enquiry privacy notice.';
            }
            if (!form.data.health_data_consent) {
                nextErrors.health_data_consent =
                    'You must consent to health data processing.';
            }
        }

        setLocalErrors(nextErrors);

        return Object.keys(nextErrors).length === 0;
    };

    const goToStep = (index: number) => {
        if (index <= maxReachedStep) {
            setLocalErrors({});
            setStep(index);
        }
    };

    const submitIntake = () => {
        if (!validateStep(3)) {
            return;
        }

        // Inertia v3: transform() returns void — do not chain .post()
        form.transform((data) => ({
            ...data,
            preferred_language: locale,
            department_id: data.department_id
                ? Number(data.department_id)
                : null,
            gender: data.gender || null,
            country_of_residence: data.country_of_residence || null,
            enquiry_consent: data.enquiry_consent ? 1 : 0,
            health_data_consent: data.health_data_consent ? 1 : 0,
            ai_processing_consent: data.ai_processing_consent ? 1 : 0,
        }));

        form.post(`/p/${organization.slug}/intake`, {
            forceFormData: true,
            onError: () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },
            onFinish: () => {
                form.transform((data) => data);
            },
        });
    };

    return (
        <div
            className="min-h-screen bg-[linear-gradient(180deg,#e8eef6_0%,#f3f6fb_42%,#eef2f8_100%)]"
            dir={rtl ? 'rtl' : 'ltr'}
        >
            <Head title={`${organization.name} · ${t('title')}`} />

            <div className="mx-auto max-w-3xl px-4 py-10">
                <div className="mb-8 space-y-4">
                    <p
                        className="text-xs font-semibold tracking-[0.16em] uppercase"
                        style={{ color: accent }}
                    >
                        {organization.name}
                    </p>
                    <div>
                        <h1 className="font-display text-3xl font-semibold tracking-tight text-slate-900">
                            {t('title')}
                        </h1>
                        <p className="mt-2 text-slate-600">{t('subtitle')}</p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <span className="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500">
                            <Languages className="size-3.5" strokeWidth={1.5} />
                            Language
                        </span>
                        {organization.supported_languages.map((language) => {
                            const active = locale === language;

                            return (
                                <button
                                    key={language}
                                    type="button"
                                    className={cn(
                                        'rounded-full border px-3 py-1.5 text-xs font-semibold tracking-wide transition',
                                        active
                                            ? 'border-transparent text-white shadow-sm'
                                            : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50',
                                    )}
                                    style={
                                        active
                                            ? { backgroundColor: accent }
                                            : undefined
                                    }
                                    onClick={() => {
                                        setLocale(language as IntakeLocale);
                                        form.setData(
                                            'preferred_language',
                                            language,
                                        );
                                    }}
                                >
                                    {language.toUpperCase()}
                                </button>
                            );
                        })}
                    </div>
                </div>

                <form
                    className="overflow-hidden rounded-[24px] border border-slate-200/80 bg-white shadow-[0_18px_50px_-36px_rgba(15,23,42,0.35)]"
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (step < 3) {
                            if (!validateStep(step)) {
                                return;
                            }

                            const next = step + 1;
                            setStep(next);
                            setMaxReachedStep((reached) =>
                                Math.max(reached, next),
                            );
                            setLocalErrors({});
                            return;
                        }

                        submitIntake();
                    }}
                >
                    <div className="border-b border-slate-100 bg-slate-50/80 px-6 py-5 md:px-7">
                        <div className="mb-4 text-sm font-medium text-slate-600">
                            Step {step + 1} of {steps.length}
                            <span className="text-slate-400"> · </span>
                            <span style={{ color: accent }}>
                                {steps[step]?.label}
                            </span>
                        </div>

                        <div className="relative w-full">
                            <div
                                aria-hidden
                                className="absolute top-[18px] end-[12.5%] start-[12.5%] h-0.5 bg-slate-200"
                            />
                            <div
                                aria-hidden
                                className="absolute top-[18px] start-[12.5%] h-0.5 transition-all duration-300"
                                style={{
                                    backgroundColor: accent,
                                    width: `${(step / (steps.length - 1)) * 75}%`,
                                }}
                            />
                            <ol className="relative z-10 grid w-full grid-cols-4 gap-2">
                                {steps.map((item, index) => {
                                    const active = index === step;
                                    const completed = index < step;
                                    const reachable = index <= maxReachedStep;

                                    return (
                                        <li
                                            key={item.label}
                                            className="flex min-w-0 flex-col items-center gap-2"
                                        >
                                            <button
                                                type="button"
                                                disabled={!reachable}
                                                onClick={() => goToStep(index)}
                                                className={cn(
                                                    'flex w-full min-w-0 flex-col items-center gap-2',
                                                    !reachable &&
                                                        'cursor-not-allowed',
                                                )}
                                            >
                                                <span
                                                    className={cn(
                                                        'flex size-9 items-center justify-center rounded-full border text-sm font-semibold transition',
                                                        active || completed
                                                            ? 'border-transparent text-white shadow-sm'
                                                            : 'border-slate-200 bg-white text-slate-400',
                                                    )}
                                                    style={
                                                        active || completed
                                                            ? {
                                                                  backgroundColor:
                                                                      accent,
                                                              }
                                                            : undefined
                                                    }
                                                >
                                                    {completed ? (
                                                        <Check
                                                            className="size-4"
                                                            strokeWidth={2.25}
                                                        />
                                                    ) : (
                                                        index + 1
                                                    )}
                                                </span>
                                                <span
                                                    className={cn(
                                                        'max-w-full truncate text-center text-[11px] font-semibold',
                                                        active
                                                            ? 'text-slate-900'
                                                            : completed
                                                              ? 'text-slate-600'
                                                              : 'text-slate-400',
                                                    )}
                                                >
                                                    {item.label}
                                                </span>
                                            </button>
                                        </li>
                                    );
                                })}
                            </ol>
                        </div>
                    </div>

                    <div className="space-y-6 bg-white p-6 md:p-7">
                    {step === 0 ? (
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field
                                label={t('firstName')}
                                error={allErrors.first_name}
                            >
                                <IconInput
                                    icon={UserRound}
                                    invalid={Boolean(allErrors.first_name)}
                                >
                                    <Input
                                        aria-invalid={Boolean(
                                            allErrors.first_name,
                                        )}
                                        className="h-11 border-0 bg-transparent px-0 shadow-none focus-visible:shadow-none focus-visible:ring-0"
                                        value={form.data.first_name}
                                        onChange={(e) =>
                                            form.setData(
                                                'first_name',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </IconInput>
                            </Field>
                            <Field
                                label={t('lastName')}
                                error={allErrors.last_name}
                            >
                                <IconInput
                                    icon={UserRound}
                                    invalid={Boolean(allErrors.last_name)}
                                >
                                    <Input
                                        aria-invalid={Boolean(
                                            allErrors.last_name,
                                        )}
                                        className="h-11 border-0 bg-transparent px-0 shadow-none focus-visible:shadow-none focus-visible:ring-0"
                                        value={form.data.last_name}
                                        onChange={(e) =>
                                            form.setData(
                                                'last_name',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </IconInput>
                            </Field>
                            <Field
                                label={t('dateOfBirth')}
                                error={allErrors.date_of_birth}
                            >
                                <IconInput
                                    icon={CalendarDays}
                                    invalid={Boolean(allErrors.date_of_birth)}
                                >
                                    <Input
                                        type="date"
                                        aria-invalid={Boolean(
                                            allErrors.date_of_birth,
                                        )}
                                        className="h-11 border-0 bg-transparent px-0 shadow-none focus-visible:shadow-none focus-visible:ring-0"
                                        value={form.data.date_of_birth}
                                        onChange={(e) =>
                                            form.setData(
                                                'date_of_birth',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </IconInput>
                            </Field>
                            <Field
                                label={t('gender')}
                                error={allErrors.gender}
                            >
                                <Select
                                    value={
                                        form.data.gender === ''
                                            ? NONE_VALUE
                                            : form.data.gender
                                    }
                                    onValueChange={(value) =>
                                        form.setData(
                                            'gender',
                                            value === NONE_VALUE ? '' : value,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        className="w-full"
                                        aria-invalid={Boolean(allErrors.gender)}
                                    >
                                        <SelectValue
                                            placeholder={t('optional')}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NONE_VALUE}>
                                            {t('optional')}
                                        </SelectItem>
                                        {genderOptions.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field
                                label={t('country')}
                                error={allErrors.country_of_residence}
                            >
                                <IconInput
                                    icon={MapPin}
                                    invalid={Boolean(
                                        allErrors.country_of_residence,
                                    )}
                                >
                                    <Input
                                        maxLength={2}
                                        aria-invalid={Boolean(
                                            allErrors.country_of_residence,
                                        )}
                                        className="h-11 border-0 bg-transparent px-0 shadow-none focus-visible:shadow-none focus-visible:ring-0"
                                        placeholder="TR"
                                        value={form.data.country_of_residence}
                                        onChange={(e) =>
                                            form.setData(
                                                'country_of_residence',
                                                e.target.value.toUpperCase(),
                                            )
                                        }
                                    />
                                </IconInput>
                            </Field>
                            <Field label={t('email')} error={allErrors.email}>
                                <IconInput
                                    icon={Mail}
                                    invalid={Boolean(allErrors.email)}
                                >
                                    <Input
                                        type="email"
                                        aria-invalid={Boolean(allErrors.email)}
                                        className="h-11 border-0 bg-transparent px-0 shadow-none focus-visible:shadow-none focus-visible:ring-0"
                                        value={form.data.email}
                                        onChange={(e) =>
                                            form.setData(
                                                'email',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </IconInput>
                            </Field>
                            <Field label={t('phone')} error={allErrors.phone}>
                                <IconInput
                                    icon={Phone}
                                    invalid={Boolean(allErrors.phone)}
                                >
                                    <Input
                                        aria-invalid={Boolean(allErrors.phone)}
                                        className="h-11 border-0 bg-transparent px-0 shadow-none focus-visible:shadow-none focus-visible:ring-0"
                                        value={form.data.phone}
                                        onChange={(e) =>
                                            form.setData(
                                                'phone',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </IconInput>
                            </Field>
                        </div>
                    ) : null}

                    {step === 1 ? (
                        <div className="grid gap-4">
                            <Field
                                label={t('department')}
                                error={allErrors.department_id}
                            >
                                <Select
                                    value={
                                        form.data.department_id === ''
                                            ? NONE_VALUE
                                            : String(form.data.department_id)
                                    }
                                    onValueChange={(value) =>
                                        form.setData(
                                            'department_id',
                                            value === NONE_VALUE ? '' : value,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        className="w-full"
                                        aria-invalid={Boolean(
                                            allErrors.department_id,
                                        )}
                                    >
                                        <SelectValue
                                            placeholder={t('optional')}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NONE_VALUE}>
                                            {t('optional')}
                                        </SelectItem>
                                        {departments.map((department) => (
                                            <SelectItem
                                                key={department.id}
                                                value={String(department.id)}
                                            >
                                                {department.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field
                                label={t('reason')}
                                error={allErrors.reason_for_contact}
                            >
                                <Textarea
                                    className="min-h-24"
                                    aria-invalid={Boolean(
                                        allErrors.reason_for_contact,
                                    )}
                                    value={form.data.reason_for_contact}
                                    onChange={(e) =>
                                        form.setData(
                                            'reason_for_contact',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                            {(
                                [
                                    ['diagnosis', 'diagnosis'],
                                    ['symptoms', 'symptoms_or_history'],
                                    ['procedures', 'previous_procedures'],
                                    ['medications', 'current_medications'],
                                    ['allergies', 'known_allergies'],
                                    ['comments', 'additional_comments'],
                                ] as const
                            ).map(([labelKey, field]) => (
                                <Field
                                    key={field}
                                    label={`${t(labelKey)} (${t('optional')})`}
                                    error={allErrors[field]}
                                >
                                    <Textarea
                                        className="min-h-20"
                                        aria-invalid={Boolean(allErrors[field])}
                                        value={form.data[field]}
                                        onChange={(e) =>
                                            form.setData(field, e.target.value)
                                        }
                                    />
                                </Field>
                            ))}
                        </div>
                    ) : null}

                    {step === 2 ? (
                        <div className="space-y-4">
                            <Field
                                label={t('documents')}
                                error={allErrors.documents}
                            >
                                <label className="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-slate-300 bg-slate-50/80 px-4 py-8 text-center transition hover:border-blue-300 hover:bg-blue-50/40">
                                    <FileText
                                        className="size-6 text-blue-600"
                                        strokeWidth={1.5}
                                    />
                                    <span className="text-sm font-medium text-slate-700">
                                        Upload PDF or images
                                    </span>
                                    <span className="text-xs text-slate-500">
                                        PDF, JPG, PNG · optional
                                    </span>
                                    <Input
                                        type="file"
                                        multiple
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        className="hidden"
                                        onChange={(e) => {
                                            const files = Array.from(
                                                e.target.files ?? [],
                                            );
                                            form.setData('documents', files);
                                            form.setData(
                                                'document_categories',
                                                files.map(
                                                    () =>
                                                        documentCategories[0]
                                                            ?.value ??
                                                        'other_supporting_document',
                                                ),
                                            );
                                        }}
                                    />
                                </label>
                            </Field>
                            {form.data.documents.map((file, index) => (
                                <div
                                    key={`${file.name}-${index}`}
                                    className="grid gap-2 rounded-xl border border-slate-200 bg-white p-3 shadow-[0_1px_2px_rgba(15,23,42,0.04),0_4px_12px_rgba(15,23,42,0.04)] md:grid-cols-[1fr_220px]"
                                >
                                    <div className="flex items-center gap-2 text-sm text-slate-700">
                                        <FileText className="size-4 text-blue-600" />
                                        {file.name}
                                    </div>
                                    <Select
                                        value={
                                            form.data.document_categories[
                                                index
                                            ] ??
                                            documentCategories[0]?.value ??
                                            NONE_VALUE
                                        }
                                        onValueChange={(value) => {
                                            const next = [
                                                ...form.data
                                                    .document_categories,
                                            ];
                                            next[index] = value;
                                            form.setData(
                                                'document_categories',
                                                next,
                                            );
                                        }}
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="Category" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {documentCategories.map(
                                                (category) => (
                                                    <SelectItem
                                                        key={category.value}
                                                        value={category.value}
                                                    >
                                                        {category.label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                </div>
                            ))}
                        </div>
                    ) : null}

                    {step === 3 ? (
                        <div className="space-y-4 text-sm text-slate-700">
                            <p className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500">
                                Privacy notice version:{' '}
                                {consentVersions.privacy_notice_version}
                            </p>
                            <label className="flex items-start gap-3 rounded-xl border border-slate-200 p-3">
                                <input
                                    type="checkbox"
                                    className="mt-0.5 size-4 rounded border-slate-300"
                                    checked={form.data.enquiry_consent}
                                    onChange={(e) =>
                                        form.setData(
                                            'enquiry_consent',
                                            e.target.checked,
                                        )
                                    }
                                />
                                <span>{t('enquiryConsent')}</span>
                            </label>
                            <InputError
                                message={allErrors.enquiry_consent}
                            />
                            <label className="flex items-start gap-3 rounded-xl border border-slate-200 p-3">
                                <input
                                    type="checkbox"
                                    className="mt-0.5 size-4 rounded border-slate-300"
                                    checked={form.data.health_data_consent}
                                    onChange={(e) =>
                                        form.setData(
                                            'health_data_consent',
                                            e.target.checked,
                                        )
                                    }
                                />
                                <span>{t('healthConsent')}</span>
                            </label>
                            <InputError
                                message={allErrors.health_data_consent}
                            />
                            <label className="flex items-start gap-3 rounded-xl border border-slate-200 p-3">
                                <input
                                    type="checkbox"
                                    className="mt-0.5 size-4 rounded border-slate-300"
                                    checked={form.data.ai_processing_consent}
                                    onChange={(e) =>
                                        form.setData(
                                            'ai_processing_consent',
                                            e.target.checked,
                                        )
                                    }
                                />
                                <span>{t('aiConsent')}</span>
                            </label>
                        </div>
                    ) : null}

                        <div className="flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                className="rounded-xl"
                                disabled={step === 0 || form.processing}
                                onClick={() => {
                                    setLocalErrors({});
                                    setStep((current) =>
                                        Math.max(current - 1, 0),
                                    );
                                }}
                            >
                                {t('back')}
                            </Button>
                            <Button
                                type="submit"
                                className="rounded-xl text-white hover:opacity-95"
                                style={{ backgroundColor: accent }}
                                disabled={form.processing}
                            >
                                {form.processing
                                    ? 'Submitting…'
                                    : step === 3
                                      ? t('submit')
                                      : t('next')}
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    );
}

function IconInput({
    icon: Icon,
    children,
    invalid = false,
}: {
    icon: typeof UserRound;
    children: React.ReactNode;
    invalid?: boolean;
}) {
    return (
        <div
            className={cn(
                'flex h-11 w-full items-center gap-2 rounded-xl border bg-white px-3 shadow-[0_1px_2px_rgba(15,23,42,0.04),0_4px_12px_rgba(15,23,42,0.04)] transition-[color,box-shadow]',
                invalid
                    ? 'border-destructive shadow-[0_0_0_4px_rgba(239,68,68,0.12)] focus-within:border-destructive focus-within:shadow-[0_0_0_4px_rgba(239,68,68,0.12)]'
                    : 'border-input focus-within:border-brand/40 focus-within:shadow-[0_1px_2px_rgba(47,111,237,0.08),0_0_0_4px_rgba(47,111,237,0.12)]',
            )}
        >
            <Icon
                className={cn(
                    'size-4 shrink-0',
                    invalid ? 'text-rose-500' : 'text-slate-400',
                )}
                strokeWidth={1.5}
            />
            <div className="min-w-0 flex-1">{children}</div>
        </div>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label className="text-slate-700">{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
