import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Bot,
    Building2,
    FileScan,
    Globe2,
    ImageIcon,
    Palette,
    Sparkles,
    Trash2,
    Upload,
} from 'lucide-react';
import { useRef, type ReactNode } from 'react';
import InputError from '@/components/input-error';
import OrganizationTabs from '@/components/organization-tabs';
import { SettingToggle } from '@/components/setting-toggle';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { OrganizationSettings, Team } from '@/types';

type LanguageOption = {
    value: string;
    label: string;
};

type Props = {
    team: Pick<Team, 'id' | 'name' | 'slug'>;
    settings: OrganizationSettings;
    permissions: {
        canManageOrganizationSettings: boolean;
    };
    languageOptions: LanguageOption[];
    logoUrl: string | null;
};

function Section({
    icon: Icon,
    title,
    description,
    children,
}: {
    icon: typeof Building2;
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <section className="overflow-hidden rounded-xl border border-border bg-card">
            <div className="flex items-start gap-3 border-b border-border px-5 py-4">
                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand">
                    <Icon className="size-4" />
                </span>
                <div>
                    <h2 className="text-sm font-semibold">{title}</h2>
                    <p className="text-xs text-muted-foreground">
                        {description}
                    </p>
                </div>
            </div>
            <div className="p-5">{children}</div>
        </section>
    );
}

function Field({
    id,
    label,
    error,
    className,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('grid gap-1.5', className)}>
            <Label htmlFor={id}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function ColorField({
    id,
    label,
    value,
    fallback,
    disabled,
    error,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    fallback: string;
    disabled: boolean;
    error?: string;
    onChange: (value: string) => void;
}) {
    const valid = /^#[0-9a-fA-F]{6}$/.test(value);

    return (
        <Field id={id} label={label} error={error}>
            <div className="flex items-center gap-2">
                <input
                    type="color"
                    aria-label={`${label} picker`}
                    value={valid ? value : fallback}
                    disabled={disabled}
                    onChange={(event) => onChange(event.target.value.toUpperCase())}
                    className="size-9 shrink-0 cursor-pointer rounded-md border border-input bg-transparent p-1 disabled:cursor-not-allowed disabled:opacity-60"
                />
                <Input
                    id={id}
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    disabled={disabled}
                    placeholder={fallback}
                    className="font-mono"
                />
            </div>
        </Field>
    );
}

function AiFeatures({
    team,
    settings,
    canEdit,
}: {
    team: Props['team'];
    settings: OrganizationSettings;
    canEdit: boolean;
}) {
    const toggle = (field: 'ai_processing_enabled' | 'assistant_enabled', value: boolean) =>
        router.patch(`/${team.slug}/organization/ai`, { [field]: value }, { preserveScroll: true });

    return (
        <Section
            icon={Sparkles}
            title="AI features"
            description="Each switch takes effect straight away for everyone in this hospital. Provider: OpenAI."
        >
            <div className="space-y-3">
                <SettingToggle
                    id="ai_processing_enabled"
                    icon={FileScan}
                    title="AI document processing"
                    description="Reads, translates and summarises patient documents. It only runs when the patient has also given AI consent, and results are always drafts for staff to review."
                    checked={settings.ai_processing_enabled}
                    disabled={!canEdit}
                    onChange={(value) => toggle('ai_processing_enabled', value)}
                />
                <SettingToggle
                    id="assistant_enabled"
                    icon={Bot}
                    title={`AI assistant (${settings.assistant_name})`}
                    description={
                        <>
                            The command center and chat bubble for staff. It only
                            sees what each person can already see, and every
                            action waits for approval.{' '}
                            <a
                                href={`/${team.slug}/assistant`}
                                className="font-medium text-brand hover:underline"
                            >
                                Open the command center
                            </a>
                        </>
                    }
                    checked={settings.assistant_enabled}
                    disabled={!canEdit}
                    onChange={(value) => toggle('assistant_enabled', value)}
                />
            </div>
            <p className="mt-3 text-xs text-muted-foreground">
                Turn these on once your hospital has approved sending patient
                information to the AI provider.
            </p>
        </Section>
    );
}

export default function OrganizationSettingsPage({
    team,
    settings,
    permissions,
    languageOptions,
    logoUrl,
}: Props) {
    const canEdit = permissions.canManageOrganizationSettings;
    const logoError = usePage().props.errors?.logo as string | undefined;
    const fileInput = useRef<HTMLInputElement>(null);

    const form = useForm({
        legal_name: settings.legal_name ?? '',
        contact_email: settings.contact_email ?? '',
        contact_phone: settings.contact_phone ?? '',
        address_line_1: settings.address_line_1 ?? '',
        address_line_2: settings.address_line_2 ?? '',
        city: settings.city ?? '',
        state: settings.state ?? '',
        postal_code: settings.postal_code ?? '',
        country: settings.country ?? '',
        timezone: settings.timezone,
        default_currency: settings.default_currency,
        default_language: settings.default_language,
        supported_languages: settings.supported_languages,
        brand_primary_color: settings.brand_primary_color ?? '',
        brand_secondary_color: settings.brand_secondary_color ?? '',
    });

    const text = (key: keyof typeof form.data) => ({
        id: key,
        value: String(form.data[key] ?? ''),
        disabled: !canEdit,
        onChange: (event: { target: { value: string } }) =>
            form.setData(key, event.target.value as never),
    });

    const toggleLanguage = (language: string) => {
        const current = form.data.supported_languages;

        form.setData(
            'supported_languages',
            current.includes(language)
                ? current.filter((value) => value !== language)
                : [...current, language],
        );
    };

    return (
        <>
            <Head title={`${team.name} settings`} />
            <OrganizationTabs />

            <form
                className="mx-auto max-w-4xl space-y-5 p-4 pb-28 md:p-6 md:pb-28"
                onSubmit={(event) => {
                    event.preventDefault();

                    if (canEdit) {
                        form.patch(`/${team.slug}/organization/settings`, {
                            preserveScroll: true,
                        });
                    }
                }}
            >
                <header className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Profile &amp; branding
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        How {team.name} appears to patients, which languages it
                        supports, and which AI features are switched on.
                    </p>
                </header>

                <AiFeatures team={team} settings={settings} canEdit={canEdit} />

                <Section
                    icon={ImageIcon}
                    title="Logo"
                    description="Shown to patients on the intake and tracking pages."
                >
                    <div className="flex flex-wrap items-center gap-5">
                        <div className="flex size-24 items-center justify-center overflow-hidden rounded-2xl border border-dashed border-border bg-muted/40">
                            {logoUrl ? (
                                <img
                                    src={logoUrl}
                                    alt={`${team.name} logo`}
                                    className="max-h-full max-w-full object-contain p-2"
                                />
                            ) : (
                                <ImageIcon className="size-7 text-muted-foreground/50" />
                            )}
                        </div>
                        {canEdit ? (
                            <div className="space-y-2">
                                <input
                                    ref={fileInput}
                                    type="file"
                                    hidden
                                    accept="image/png,image/jpeg,image/webp"
                                    aria-label="Upload logo"
                                    onChange={(event) => {
                                        const file = event.target.files?.[0];

                                        if (file) {
                                            router.post(
                                                `/${team.slug}/organization/logo`,
                                                { logo: file },
                                                {
                                                    forceFormData: true,
                                                    preserveScroll: true,
                                                },
                                            );
                                        }

                                        event.target.value = '';
                                    }}
                                />
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => fileInput.current?.click()}
                                    >
                                        <Upload className="size-4" />
                                        {logoUrl ? 'Replace logo' : 'Upload logo'}
                                    </Button>
                                    {logoUrl ? (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="text-destructive"
                                            onClick={() =>
                                                router.delete(
                                                    `/${team.slug}/organization/logo`,
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Trash2 className="size-4" />
                                            Remove
                                        </Button>
                                    ) : null}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    PNG, JPG or WebP, up to 1 MB.
                                </p>
                            </div>
                        ) : null}
                    </div>
                    <InputError message={logoError} className="mt-2" />
                </Section>

                <Section
                    icon={Building2}
                    title="Hospital profile"
                    description="Contact details and address."
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field id="legal_name" label="Legal name" error={form.errors.legal_name}>
                            <Input {...text('legal_name')} />
                        </Field>
                        <Field id="contact_email" label="Contact email" error={form.errors.contact_email}>
                            <Input type="email" {...text('contact_email')} />
                        </Field>
                        <Field id="contact_phone" label="Contact phone" error={form.errors.contact_phone}>
                            <Input {...text('contact_phone')} />
                        </Field>
                        <Field id="country" label="Country code" error={form.errors.country}>
                            <Input
                                {...text('country')}
                                maxLength={2}
                                placeholder="TR"
                                onChange={(event) =>
                                    form.setData('country', event.target.value.toUpperCase())
                                }
                            />
                        </Field>
                        <Field id="address_line_1" label="Address" className="md:col-span-2" error={form.errors.address_line_1}>
                            <Input {...text('address_line_1')} />
                        </Field>
                        <Field id="address_line_2" label="Address line 2" className="md:col-span-2" error={form.errors.address_line_2}>
                            <Input {...text('address_line_2')} />
                        </Field>
                        <div className="grid gap-4 md:col-span-2 md:grid-cols-3">
                            <Field id="city" label="City" error={form.errors.city}>
                                <Input {...text('city')} />
                            </Field>
                            <Field id="state" label="State / region" error={form.errors.state}>
                                <Input {...text('state')} />
                            </Field>
                            <Field id="postal_code" label="Postal code" error={form.errors.postal_code}>
                                <Input {...text('postal_code')} />
                            </Field>
                        </div>
                    </div>
                </Section>

                <Section
                    icon={Globe2}
                    title="Language & region"
                    description="Defaults for dates, prices and the patient portal."
                >
                    <div className="grid gap-4 md:grid-cols-3">
                        <Field id="timezone" label="Timezone" error={form.errors.timezone}>
                            <Input {...text('timezone')} required placeholder="Europe/Istanbul" />
                        </Field>
                        <Field id="default_currency" label="Currency" error={form.errors.default_currency}>
                            <Input
                                {...text('default_currency')}
                                required
                                maxLength={3}
                                placeholder="USD"
                                onChange={(event) =>
                                    form.setData('default_currency', event.target.value.toUpperCase())
                                }
                            />
                        </Field>
                        <Field id="default_language" label="Staff working language" error={form.errors.default_language}>
                            <Input {...text('default_language')} required placeholder="en" />
                        </Field>
                    </div>

                    <div className="mt-5 space-y-2.5">
                        <Label>Languages patients can use</Label>
                        <div className="flex flex-wrap gap-2">
                            {languageOptions.map((language) => {
                                const active = form.data.supported_languages.includes(language.value);

                                return (
                                    <button
                                        key={language.value}
                                        type="button"
                                        aria-pressed={active}
                                        disabled={!canEdit}
                                        onClick={() => toggleLanguage(language.value)}
                                        className={cn(
                                            'rounded-full border px-3.5 py-1.5 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-60',
                                            active
                                                ? 'border-brand bg-brand/10 text-brand'
                                                : 'border-border text-muted-foreground hover:bg-muted',
                                        )}
                                    >
                                        {language.label}
                                    </button>
                                );
                            })}
                        </div>
                        <InputError message={form.errors.supported_languages} />
                    </div>
                </Section>

                <Section
                    icon={Palette}
                    title="Brand colours"
                    description="Used on the patient intake and tracking pages."
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <ColorField
                            id="brand_primary_color"
                            label="Primary colour"
                            value={form.data.brand_primary_color}
                            fallback="#5F7D45"
                            disabled={!canEdit}
                            error={form.errors.brand_primary_color}
                            onChange={(value) => form.setData('brand_primary_color', value)}
                        />
                        <ColorField
                            id="brand_secondary_color"
                            label="Secondary colour"
                            value={form.data.brand_secondary_color}
                            fallback="#134E4A"
                            disabled={!canEdit}
                            error={form.errors.brand_secondary_color}
                            onChange={(value) => form.setData('brand_secondary_color', value)}
                        />
                    </div>
                </Section>

                {canEdit ? (
                    <div className="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-background/90 backdrop-blur md:left-[var(--sidebar-width,16rem)]">
                        <div className="mx-auto flex max-w-4xl items-center justify-between gap-3 px-4 py-3 md:px-6">
                            <p className="text-sm text-muted-foreground">
                                {form.isDirty ? 'You have unsaved changes.' : 'All changes saved.'}
                            </p>
                            <div className="flex gap-2">
                                {form.isDirty ? (
                                    <Button type="button" variant="ghost" onClick={() => form.reset()}>
                                        Discard
                                    </Button>
                                ) : null}
                                <Button
                                    type="submit"
                                    disabled={form.processing || !form.isDirty}
                                    data-test="save-organization-settings"
                                >
                                    Save settings
                                </Button>
                            </div>
                        </div>
                    </div>
                ) : null}
            </form>
        </>
    );
}

OrganizationSettingsPage.layout = (props: {
    team: { name: string; slug: string };
}) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(props.team.slug),
        },
        {
            title: 'Organization settings',
            href: `/${props.team.slug}/organization/settings`,
        },
    ],
});
