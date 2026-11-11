import { Head, router, useForm } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import OrganizationTabs from '@/components/organization-tabs';
import { ToggleField } from '@/components/toggle-field';

type Props = {
    team: { id: number; name: string; slug: string };
    driver: string;
    driverOptions: Array<{ value: string; label: string }>;
    config: {
        key_hint: string | null;
        region: string;
        bucket: string;
        endpoint: string;
        use_path_style_endpoint: boolean;
        cloud_name: string;
        api_key_hint: string | null;
        has_secret: boolean;
    };
    externalDocuments: boolean;
    documentCounts: { platform: number; external: number };
};

function Field({
    id,
    label,
    hint,
    error,
    children,
}: {
    id: string;
    label: string;
    hint?: string | null;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint ? (
                <p className="text-xs text-muted-foreground">{hint}</p>
            ) : null}
            <InputError message={error} />
        </div>
    );
}

export default function Storage({
    team,
    driver,
    driverOptions,
    config,
    externalDocuments,
    documentCounts,
}: Props) {
    const form = useForm({
        driver,
        key: '',
        secret: '',
        region: config.region,
        bucket: config.bucket,
        endpoint: config.endpoint,
        use_path_style_endpoint: config.use_path_style_endpoint,
        cloud_name: config.cloud_name,
        api_key: '',
        api_secret: '',
    });

    const sameDriver = form.data.driver === driver;
    const secretHint = (stored: boolean) =>
        sameDriver && stored ? 'Saved. Leave blank to keep it.' : null;

    return (
        <>
            <Head title="Document storage" />
            <OrganizationTabs />
            <div className="mx-auto max-w-3xl space-y-6 p-4 md:p-6">
                <header className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Document storage
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Choose where your patients&apos; documents are kept.
                        Files are always private: they are only ever opened
                        through CareFlow after a staff permission check, never
                        through a public link.
                    </p>
                </header>

                <form
                    className="space-y-6 rounded-xl border border-border bg-card p-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(`/${team.slug}/organization/storage`, {
                            preserveScroll: true,
                            onSuccess: () => {
                                form.setData({
                                    ...form.data,
                                    key: '',
                                    secret: '',
                                    api_key: '',
                                    api_secret: '',
                                });
                            },
                        });
                    }}
                >
                    <fieldset className="space-y-2">
                        <legend className="text-sm font-medium">
                            Storage location
                        </legend>
                        {driverOptions.map((option) => (
                            <label
                                key={option.value}
                                className="flex cursor-pointer items-start gap-2 text-sm"
                            >
                                <input
                                    type="radio"
                                    name="driver"
                                    className="mt-1"
                                    checked={form.data.driver === option.value}
                                    onChange={() =>
                                        form.setData('driver', option.value)
                                    }
                                />
                                <span>{option.label}</span>
                            </label>
                        ))}
                        <InputError message={form.errors.driver} />
                    </fieldset>

                    {externalDocuments ? (
                        <p className="rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-900 dark:text-amber-100">
                            Some documents are already stored in your own
                            storage. You can update credentials for the same
                            bucket or account, but switching to another target
                            is blocked so no document is orphaned.
                        </p>
                    ) : null}

                    {form.data.driver === 's3' ? (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                id="bucket"
                                label="Bucket name"
                                error={form.errors.bucket}
                            >
                                <Input
                                    id="bucket"
                                    value={form.data.bucket}
                                    onChange={(event) =>
                                        form.setData('bucket', event.target.value)
                                    }
                                />
                            </Field>
                            <Field
                                id="region"
                                label="Region"
                                error={form.errors.region}
                            >
                                <Input
                                    id="region"
                                    value={form.data.region}
                                    placeholder="eu-central-1"
                                    onChange={(event) =>
                                        form.setData('region', event.target.value)
                                    }
                                />
                            </Field>
                            <Field
                                id="key"
                                label="Access key ID"
                                hint={
                                    sameDriver && config.key_hint
                                        ? `Saved (${config.key_hint}). Leave blank to keep it.`
                                        : null
                                }
                                error={form.errors.key}
                            >
                                <Input
                                    id="key"
                                    autoComplete="off"
                                    value={form.data.key}
                                    onChange={(event) =>
                                        form.setData('key', event.target.value)
                                    }
                                />
                            </Field>
                            <Field
                                id="secret"
                                label="Secret access key"
                                hint={secretHint(config.has_secret)}
                                error={form.errors.secret}
                            >
                                <Input
                                    id="secret"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.secret}
                                    onChange={(event) =>
                                        form.setData('secret', event.target.value)
                                    }
                                />
                            </Field>
                            <div className="sm:col-span-2">
                                <Field
                                    id="endpoint"
                                    label="Custom endpoint (optional)"
                                    hint="For Cloudflare R2, MinIO, Wasabi and other S3-compatible services. Must be https and publicly reachable."
                                    error={form.errors.endpoint}
                                >
                                    <Input
                                        id="endpoint"
                                        value={form.data.endpoint}
                                        placeholder="https://<account>.r2.cloudflarestorage.com"
                                        onChange={(event) =>
                                            form.setData(
                                                'endpoint',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                            <ToggleField
                                id="use_path_style_endpoint"
                                className="sm:col-span-2"
                                label="Path-style addressing"
                                description="Needed by MinIO and some other S3-compatible services. Leave off for Amazon S3 and Cloudflare R2."
                                checked={form.data.use_path_style_endpoint}
                                onChange={(value) =>
                                    form.setData('use_path_style_endpoint', value)
                                }
                            />
                        </div>
                    ) : null}

                    {form.data.driver === 'cloudinary' ? (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                id="cloud_name"
                                label="Cloud name"
                                error={form.errors.cloud_name}
                            >
                                <Input
                                    id="cloud_name"
                                    value={form.data.cloud_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'cloud_name',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                id="api_key"
                                label="API key"
                                hint={
                                    sameDriver && config.api_key_hint
                                        ? `Saved (${config.api_key_hint}). Leave blank to keep it.`
                                        : null
                                }
                                error={form.errors.api_key}
                            >
                                <Input
                                    id="api_key"
                                    autoComplete="off"
                                    value={form.data.api_key}
                                    onChange={(event) =>
                                        form.setData('api_key', event.target.value)
                                    }
                                />
                            </Field>
                            <div className="sm:col-span-2">
                                <Field
                                    id="api_secret"
                                    label="API secret"
                                    hint={secretHint(config.has_secret)}
                                    error={form.errors.api_secret}
                                >
                                    <Input
                                        id="api_secret"
                                        type="password"
                                        autoComplete="new-password"
                                        value={form.data.api_secret}
                                        onChange={(event) =>
                                            form.setData(
                                                'api_secret',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                            <p className="text-xs text-muted-foreground sm:col-span-2">
                                Documents are stored as private (authenticated)
                                files. Confirm with your compliance team that
                                Cloudinary is acceptable for medical records in
                                your country; a private bucket in your own
                                region is usually the safer choice.
                            </p>
                        </div>
                    ) : null}

                    <div className="flex items-start gap-2 rounded-lg bg-muted/40 p-3 text-xs text-muted-foreground">
                        <LockKeyhole className="mt-0.5 size-4 shrink-0" />
                        <span>
                            Credentials are encrypted before they are saved and
                            are never shown again. When you save, CareFlow
                            writes, reads back and deletes a small test file to
                            prove access works. Only new uploads use the new
                            storage; earlier documents stay where they are.
                        </span>
                    </div>

                    <Button type="submit" disabled={form.processing}>
                        {form.data.driver === 'platform'
                            ? 'Save'
                            : 'Test connection and save'}
                    </Button>
                </form>

                <section className="space-y-3 rounded-xl border border-border bg-card p-5">
                    <div>
                        <h2 className="text-sm font-semibold">
                            Existing documents
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            {documentCounts.platform} on CareFlow storage ·{' '}
                            {documentCounts.external} on your own storage. Each
                            file is copied, checked against its fingerprint,
                            and only then removed from the old place.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {driver !== 'platform' ? (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={documentCounts.platform === 0}
                                onClick={() =>
                                    router.post(
                                        `/${team.slug}/organization/storage/move`,
                                        { direction: 'to_external' },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Move {documentCounts.platform} to your storage
                            </Button>
                        ) : null}
                        <Button
                            type="button"
                            variant="outline"
                            disabled={documentCounts.external === 0}
                            onClick={() =>
                                router.post(
                                    `/${team.slug}/organization/storage/move`,
                                    { direction: 'to_platform' },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Move {documentCounts.external} back to CareFlow
                            storage
                        </Button>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        To leave your own storage, move its documents back
                        first. Keep it connected until the count reaches zero.
                    </p>
                </section>
            </div>
        </>
    );
}
