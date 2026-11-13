import { Head, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { AppSelect } from '@/components/app-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import OrganizationTabs from '@/components/organization-tabs';
import { ToggleField } from '@/components/toggle-field';

type Template = {
    uuid: string;
    key: string;
    key_label: string;
    name: string;
    channel: string;
    channel_label: string;
    is_active: boolean;
    subject_translations: Record<string, string>;
    body_translations: Record<string, string>;
    notes: string | null;
};

type Props = {
    team: { id: number; name: string; slug: string };
    templates: Template[];
    languages: string[];
    channel_options: Array<{ value: string; label: string }>;
    key_options: Array<{ value: string; label: string }>;
    urls: { upsert: string };
};

function translationMap(
    source: Record<string, string> | undefined,
    languages: string[],
): Record<string, string> {
    return Object.fromEntries(
        languages.map((language) => [language, source?.[language] ?? '']),
    );
}

export default function MessageTemplatesIndex({
    team,
    templates,
    languages,
    channel_options,
    urls,
}: Props) {
    const [selectedKey, setSelectedKey] = useState(
        templates[0]?.key ?? 'enquiry_received',
    );
    const [previewLanguage, setPreviewLanguage] = useState(
        languages[0] ?? 'en',
    );
    const selected =
        templates.find((template) => template.key === selectedKey) ??
        templates[0];

    const form = useForm({
        key: selected?.key ?? 'enquiry_received',
        name: selected?.name ?? '',
        channel: selected?.channel ?? 'email',
        is_active: selected?.is_active ?? true,
        subject_translations: translationMap(
            selected?.subject_translations,
            languages,
        ),
        body_translations: translationMap(
            selected?.body_translations,
            languages,
        ),
        notes: selected?.notes ?? '',
    });

    const loadTemplate = (key: string) => {
        const template = templates.find((item) => item.key === key);
        setSelectedKey(key);
        if (!template) {
            return;
        }

        form.setData({
            key: template.key,
            name: template.name,
            channel: template.channel,
            is_active: template.is_active,
            subject_translations: translationMap(
                template.subject_translations,
                languages,
            ),
            body_translations: translationMap(
                template.body_translations,
                languages,
            ),
            notes: template.notes ?? '',
        });
    };

    const previewSubject = useMemo(
        () =>
            form.data.subject_translations[previewLanguage] ||
            form.data.subject_translations.en ||
            '',
        [form.data.subject_translations, previewLanguage],
    );

    const previewBody = useMemo(
        () =>
            form.data.body_translations[previewLanguage] ||
            form.data.body_translations.en ||
            '',
        [form.data.body_translations, previewLanguage],
    );

    return (
        <>
            <Head title="Message templates" />
            <OrganizationTabs />
            <div className="space-y-8 p-4 md:p-6">
                <Heading
                    title="Message templates"
                    description="Organization-specific templates with translation support. External sends still require staff approval."
                />

                <div className="grid gap-6 lg:grid-cols-[240px_1fr]">
                    <aside className="space-y-2">
                        {templates.map((template) => (
                            <button
                                key={template.uuid}
                                type="button"
                                className={`w-full rounded-md border px-3 py-2 text-left text-sm ${
                                    selectedKey === template.key
                                        ? 'border-brand/40 bg-brand/10'
                                        : 'border-input'
                                }`}
                                onClick={() => loadTemplate(template.key)}
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <span className="font-medium">
                                        {template.key_label}
                                    </span>
                                    {!template.is_active ? (
                                        <Badge variant="outline">
                                            Inactive
                                        </Badge>
                                    ) : null}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {template.channel_label}
                                </div>
                            </button>
                        ))}
                    </aside>

                    <div className="space-y-4">
                        <form
                            className="space-y-4 rounded-xl border p-5"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form
                                    .transform((data) => ({
                                        ...data,
                                        subject_translations:
                                            Object.fromEntries(
                                                Object.entries(
                                                    data.subject_translations,
                                                ).filter(
                                                    ([, value]) =>
                                                        value.trim() !== '',
                                                ),
                                            ),
                                        body_translations: Object.fromEntries(
                                            Object.entries(
                                                data.body_translations,
                                            ).filter(
                                                ([, value]) =>
                                                    value.trim() !== '',
                                            ),
                                        ),
                                    }))
                                    .put(urls.upsert, {
                                        preserveScroll: true,
                                    });
                            }}
                        >
                            <div className="grid gap-3 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label>Name</Label>
                                    <input
                                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Channel</Label>
                                    <AppSelect
                                        aria-label="Channel"
                                        value={form.data.channel}
                                        options={channel_options}
                                        onChange={(value) =>
                                            form.setData('channel', value)
                                        }
                                    />
                                </div>
                                <ToggleField
                                    id="is_active"
                                    className="md:col-span-2"
                                    label="Active template"
                                    description="Inactive templates are kept but not offered when writing to patients."
                                    checked={form.data.is_active}
                                    onChange={(value) => form.setData('is_active', value)}
                                />
                            </div>

                            <div className="space-y-4">
                                {languages.map((language) => (
                                    <div
                                        key={language}
                                        className="space-y-3 rounded-lg border border-border p-4"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <h3 className="text-sm font-medium uppercase">
                                                {language}
                                            </h3>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    setPreviewLanguage(language)
                                                }
                                            >
                                                Preview
                                            </Button>
                                        </div>
                                        <div className="grid gap-2">
                                            <Label>
                                                Subject ({language.toUpperCase()}
                                                )
                                            </Label>
                                            <input
                                                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                                value={
                                                    form.data
                                                        .subject_translations[
                                                        language
                                                    ] ?? ''
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        'subject_translations',
                                                        {
                                                            ...form.data
                                                                .subject_translations,
                                                            [language]:
                                                                event.target
                                                                    .value,
                                                        },
                                                    )
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label>
                                                Body ({language.toUpperCase()})
                                            </Label>
                                            <textarea
                                                className="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                                value={
                                                    form.data.body_translations[
                                                        language
                                                    ] ?? ''
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        'body_translations',
                                                        {
                                                            ...form.data
                                                                .body_translations,
                                                            [language]:
                                                                event.target
                                                                    .value,
                                                        },
                                                    )
                                                }
                                            />
                                        </div>
                                    </div>
                                ))}
                                <p className="text-xs text-muted-foreground">
                                    Placeholders: {'{{patient_name}}'},{' '}
                                    {'{{case_reference}}'},{' '}
                                    {'{{organization_name}}'}
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label>Internal notes</Label>
                                <textarea
                                    className="min-h-20 rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                    value={form.data.notes}
                                    onChange={(event) =>
                                        form.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>

                            <InputError
                                message={form.errors.body_translations}
                            />
                            <Button type="submit" disabled={form.processing}>
                                Save template
                            </Button>
                        </form>

                        <div className="rounded-xl border border-border bg-muted/30 p-5">
                            <div className="mb-3 flex items-center justify-between gap-2">
                                <h3 className="text-sm font-semibold">
                                    Preview
                                </h3>
                                <AppSelect
                                    aria-label="Preview language"
                                    className="h-8 w-24 text-xs"
                                    value={previewLanguage}
                                    options={languages.map((language) => ({
                                        value: language,
                                        label: language.toUpperCase(),
                                    }))}
                                    onChange={setPreviewLanguage}
                                />
                            </div>
                            <p className="text-sm font-medium">
                                {previewSubject || 'No subject'}
                            </p>
                            <p className="mt-2 whitespace-pre-wrap text-sm text-muted-foreground">
                                {previewBody || 'No body content'}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

MessageTemplatesIndex.layout = (props: { team: { slug: string } }) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard(props.team.slug) },
        {
            title: 'Message templates',
            href: `/${props.team.slug}/organization/message-templates`,
        },
    ],
});
