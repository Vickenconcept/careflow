import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
};

export default function OrganizationSettingsPage({
    team,
    settings,
    permissions,
    languageOptions,
}: Props) {
    const canEdit = permissions.canManageOrganizationSettings;

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
        ai_processing_enabled: settings.ai_processing_enabled,
        ai_provider: settings.ai_provider || 'openai',
    });

    const toggleLanguage = (language: string, checked: boolean) => {
        const next = checked
            ? [...form.data.supported_languages, language]
            : form.data.supported_languages.filter(
                  (value) => value !== language,
              );

        form.setData('supported_languages', next);
    };

    return (
        <>
            <Head title={`${team.name} settings`} />

            <div className="flex flex-col space-y-8">
                <Heading
                    title="Organization settings"
                    description="Hospital profile, localization, branding, and AI processing configuration."
                />

                <form
                    className="space-y-8"
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (!canEdit) {
                            return;
                        }

                        form.patch(
                            `/` +
                                team.slug +
                                `/organization/settings`,
                            { preserveScroll: true },
                        );
                    }}
                >
                    <section className="space-y-4">
                        <h2 className="text-base font-medium">Profile</h2>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="legal_name">Legal name</Label>
                                <Input
                                    id="legal_name"
                                    value={form.data.legal_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'legal_name',
                                            event.target.value,
                                        )
                                    }
                                    disabled={!canEdit}
                                />
                                <InputError message={form.errors.legal_name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="contact_email">
                                    Contact email
                                </Label>
                                <Input
                                    id="contact_email"
                                    type="email"
                                    value={form.data.contact_email}
                                    onChange={(event) =>
                                        form.setData(
                                            'contact_email',
                                            event.target.value,
                                        )
                                    }
                                    disabled={!canEdit}
                                />
                                <InputError
                                    message={form.errors.contact_email}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="contact_phone">
                                    Contact phone
                                </Label>
                                <Input
                                    id="contact_phone"
                                    value={form.data.contact_phone}
                                    onChange={(event) =>
                                        form.setData(
                                            'contact_phone',
                                            event.target.value,
                                        )
                                    }
                                    disabled={!canEdit}
                                />
                                <InputError
                                    message={form.errors.contact_phone}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="country">Country (ISO)</Label>
                                <Input
                                    id="country"
                                    maxLength={2}
                                    value={form.data.country}
                                    onChange={(event) =>
                                        form.setData(
                                            'country',
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                    disabled={!canEdit}
                                />
                                <InputError message={form.errors.country} />
                            </div>
                            <div className="grid gap-2 md:col-span-2">
                                <Label htmlFor="address_line_1">
                                    Address line 1
                                </Label>
                                <Input
                                    id="address_line_1"
                                    value={form.data.address_line_1}
                                    onChange={(event) =>
                                        form.setData(
                                            'address_line_1',
                                            event.target.value,
                                        )
                                    }
                                    disabled={!canEdit}
                                />
                                <InputError
                                    message={form.errors.address_line_1}
                                />
                            </div>
                            <div className="grid gap-2 md:col-span-2">
                                <Label htmlFor="address_line_2">
                                    Address line 2
                                </Label>
                                <Input
                                    id="address_line_2"
                                    value={form.data.address_line_2}
                                    onChange={(event) =>
                                        form.setData(
                                            'address_line_2',
                                            event.target.value,
                                        )
                                    }
                                    disabled={!canEdit}
                                />
                                <InputError
                                    message={form.errors.address_line_2}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="city">City</Label>
                                <Input
                                    id="city"
                                    value={form.data.city}
                                    onChange={(event) =>
                                        form.setData('city', event.target.value)
                                    }
                                    disabled={!canEdit}
                                />
                                <InputError message={form.errors.city} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="state">State</Label>
                                <Input
                                    id="state"
                                    value={form.data.state}
                                    onChange={(event) =>
                                        form.setData(
                                            'state',
                                            event.target.value,
                                        )
                                    }
                                    disabled={!canEdit}
                                />
                                <InputError message={form.errors.state} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="postal_code">
                                    Postal code
                                </Label>
                                <Input
                                    id="postal_code"
                                    value={form.data.postal_code}
                                    onChange={(event) =>
                                        form.setData(
                                            'postal_code',
                                            event.target.value,
                                        )
                                    }
                                    disabled={!canEdit}
                                />
                                <InputError
                                    message={form.errors.postal_code}
                                />
                            </div>
                        </div>
                    </section>

                    <section className="space-y-4">
                        <h2 className="text-base font-medium">Localization</h2>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="grid gap-2">
                                <Label htmlFor="timezone">Timezone</Label>
                                <Input
                                    id="timezone"
                                    value={form.data.timezone}
                                    onChange={(event) =>
                                        form.setData(
                                            'timezone',
                                            event.target.value,
                                        )
                                    }
                                    disabled={!canEdit}
                                    required
                                />
                                <InputError message={form.errors.timezone} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="default_currency">
                                    Default currency
                                </Label>
                                <Input
                                    id="default_currency"
                                    maxLength={3}
                                    value={form.data.default_currency}
                                    onChange={(event) =>
                                        form.setData(
                                            'default_currency',
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                    disabled={!canEdit}
                                    required
                                />
                                <InputError
                                    message={form.errors.default_currency}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="default_language">
                                    Default language
                                </Label>
                                <Input
                                    id="default_language"
                                    value={form.data.default_language}
                                    onChange={(event) =>
                                        form.setData(
                                            'default_language',
                                            event.target.value,
                                        )
                                    }
                                    disabled={!canEdit}
                                    required
                                />
                                <InputError
                                    message={form.errors.default_language}
                                />
                            </div>
                        </div>
                        <div className="space-y-3">
                            <Label>Supported patient languages</Label>
                            <div className="grid gap-3 sm:grid-cols-2 md:grid-cols-3">
                                {languageOptions.map((language) => {
                                    const checked =
                                        form.data.supported_languages.includes(
                                            language.value,
                                        );

                                    return (
                                        <label
                                            key={language.value}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                className="size-4 rounded border"
                                                checked={checked}
                                                disabled={!canEdit}
                                                onChange={(event) =>
                                                    toggleLanguage(
                                                        language.value,
                                                        event.target.checked,
                                                    )
                                                }
                                            />
                                            {language.label}
                                        </label>
                                    );
                                })}
                            </div>
                            <InputError
                                message={form.errors.supported_languages}
                            />
                        </div>
                    </section>

                    <section className="space-y-4">
                        <h2 className="text-base font-medium">Branding</h2>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="brand_primary_color">
                                    Primary color
                                </Label>
                                <Input
                                    id="brand_primary_color"
                                    value={form.data.brand_primary_color}
                                    onChange={(event) =>
                                        form.setData(
                                            'brand_primary_color',
                                            event.target.value,
                                        )
                                    }
                                    disabled={!canEdit}
                                    placeholder="#2F6FED"
                                />
                                <InputError
                                    message={form.errors.brand_primary_color}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="brand_secondary_color">
                                    Secondary color
                                </Label>
                                <Input
                                    id="brand_secondary_color"
                                    value={form.data.brand_secondary_color}
                                    onChange={(event) =>
                                        form.setData(
                                            'brand_secondary_color',
                                            event.target.value,
                                        )
                                    }
                                    disabled={!canEdit}
                                    placeholder="#134E4A"
                                />
                                <InputError
                                    message={form.errors.brand_secondary_color}
                                />
                            </div>
                        </div>
                    </section>

                    <section className="space-y-4">
                        <h2 className="text-base font-medium">
                            AI configuration
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Live processing uses{' '}
                            <code>CAREFLOW_AI_PROVIDER=openai</code> and{' '}
                            <code>OPENAI_API_KEY</code>. Keep a queue worker
                            running (<code>php artisan queue:work</code> or{' '}
                            <code>composer run dev</code>). Do not send real
                            patient documents to OpenAI without approved
                            safeguards.
                        </p>
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                className="size-4 rounded border"
                                checked={form.data.ai_processing_enabled}
                                disabled={!canEdit}
                                onChange={(event) =>
                                    form.setData(
                                        'ai_processing_enabled',
                                        event.target.checked,
                                    )
                                }
                            />
                            Enable AI document processing
                        </label>
                        <InputError
                            message={form.errors.ai_processing_enabled}
                        />
                        <div className="grid gap-2">
                            <Label htmlFor="ai_provider">Preferred AI provider</Label>
                            <select
                                id="ai_provider"
                                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                value={form.data.ai_provider}
                                disabled={!canEdit}
                                onChange={(event) =>
                                    form.setData(
                                        'ai_provider',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="openai">
                                    OpenAI (Laravel AI SDK)
                                </option>
                            </select>
                            <InputError message={form.errors.ai_provider} />
                        </div>
                    </section>

                    {canEdit ? (
                        <Button
                            type="submit"
                            disabled={form.processing}
                            data-test="save-organization-settings"
                        >
                            Save settings
                        </Button>
                    ) : null}
                </form>
            </div>
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
