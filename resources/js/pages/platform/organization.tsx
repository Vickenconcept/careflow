import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import InputError from '@/components/input-error';
import { AppSelect } from '@/components/app-select';
import { ToggleField } from '@/components/toggle-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Option = { value: string; label: string };

type Props = {
    organization: { id: number; name: string; slug: string };
    subscription: {
        plan: string;
        subscription_status: string;
        seat_limit: number | null;
        locked_modules: string[];
        platform_notes: string | null;
    };
    planOptions: Option[];
    statusOptions: Option[];
    moduleOptions: Option[];
    usage: {
        staff: number;
        pending_invitations: number;
        cases_total: number;
        cases_30_days: number;
        documents: number;
        storage_mb: number;
    };
};

export default function PlatformOrganization({
    organization,
    subscription,
    planOptions,
    statusOptions,
    moduleOptions,
    usage,
}: Props) {
    const form = useForm({
        plan: subscription.plan,
        subscription_status: subscription.subscription_status,
        seat_limit: subscription.seat_limit === null ? '' : String(subscription.seat_limit),
        locked_modules: subscription.locked_modules,
        platform_notes: subscription.platform_notes ?? '',
    });

    const usageTiles: Array<[string, string]> = [
        [
            'Staff seats',
            `${usage.staff}${form.data.seat_limit ? ` / ${form.data.seat_limit}` : ''}`,
        ],
        ['Pending invitations', String(usage.pending_invitations)],
        ['Cases (all time)', String(usage.cases_total)],
        ['New cases (30 days)', String(usage.cases_30_days)],
        ['Documents', String(usage.documents)],
        ['Storage', `${usage.storage_mb} MB`],
    ];

    return (
        <>
            <Head title={`${organization.name} subscription`} />
            <div className="mx-auto max-w-4xl space-y-6 p-4 md:p-8">
                <Button asChild variant="ghost" size="sm" className="-ml-2">
                    <Link href="/platform">
                        <ArrowLeft className="size-4" />
                        Platform admin
                    </Link>
                </Button>

                <header className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {organization.name}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Plan, module entitlements and usage. Plans are recorded
                        here by hand; no payment provider is connected, so
                        invoicing happens outside CareFlow.
                    </p>
                </header>

                <section className="grid gap-3 sm:grid-cols-3">
                    {usageTiles.map(([label, value]) => (
                        <div
                            key={label}
                            className="rounded-xl border border-border bg-card p-4"
                        >
                            <p className="text-xs text-muted-foreground">{label}</p>
                            <p className="mt-1 text-xl font-semibold tabular-nums">
                                {value}
                            </p>
                        </div>
                    ))}
                </section>

                <form
                    className="space-y-6 rounded-xl border border-border bg-card p-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({
                            ...data,
                            seat_limit: data.seat_limit === '' ? null : Number(data.seat_limit),
                        }));
                        form.patch(`/platform/organizations/${organization.slug}`, {
                            preserveScroll: true,
                        });
                    }}
                >
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="plan">Plan</Label>
                            <AppSelect
                                id="plan"
                                value={form.data.plan}
                                options={planOptions}
                                onChange={(value) => form.setData('plan', value)}
                            />
                            <InputError message={form.errors.plan} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="subscription_status">Status</Label>
                            <AppSelect
                                id="subscription_status"
                                value={form.data.subscription_status}
                                options={statusOptions}
                                onChange={(value) => form.setData('subscription_status', value)}
                            />
                            <InputError message={form.errors.subscription_status} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="seat_limit">Staff seat limit</Label>
                            <Input
                                id="seat_limit"
                                type="number"
                                min={1}
                                value={form.data.seat_limit}
                                onChange={(event) =>
                                    form.setData('seat_limit', event.target.value)
                                }
                                placeholder="Unlimited"
                            />
                            <InputError message={form.errors.seat_limit} />
                        </div>
                    </div>

                    {form.data.subscription_status === 'suspended' ? (
                        <p className="rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-900 dark:text-amber-100">
                            Suspended organizations&apos; staff are blocked from
                            signing in to the workspace. Platform admins can still
                            enter by impersonating a member.
                        </p>
                    ) : null}

                    <fieldset className="space-y-2">
                        <legend className="text-sm font-medium">
                            Modules included in this plan
                        </legend>
                        <p className="text-xs text-muted-foreground">
                            Switch a module off to hide and block it for this
                            organization. Its admins cannot switch it back on.
                        </p>
                        <div className="divide-y divide-border overflow-hidden rounded-xl border border-border">
                            {moduleOptions.map((option) => {
                                const included = !form.data.locked_modules.includes(option.value);

                                return (
                                    <ToggleField
                                        key={option.value}
                                        id={`plan-module-${option.value}`}
                                        label={option.label}
                                        description={included ? 'Included' : 'Not included: hidden for this organization'}
                                        checked={included}
                                        boxed={false}
                                        className="px-4 py-3"
                                        onChange={(value) =>
                                            form.setData(
                                                'locked_modules',
                                                value
                                                    ? form.data.locked_modules.filter(
                                                          (module) => module !== option.value,
                                                      )
                                                    : [...form.data.locked_modules, option.value],
                                            )
                                        }
                                    />
                                );
                            })}
                        </div>
                    </fieldset>

                    <div className="grid gap-1.5">
                        <Label htmlFor="platform_notes">Internal notes</Label>
                        <textarea
                            id="platform_notes"
                            rows={3}
                            value={form.data.platform_notes}
                            onChange={(event) =>
                                form.setData('platform_notes', event.target.value)
                            }
                            className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            placeholder="Contract dates, billing contact, special arrangements"
                        />
                        <InputError message={form.errors.platform_notes} />
                    </div>

                    <Button type="submit" disabled={form.processing}>
                        Save subscription
                    </Button>
                </form>
            </div>
        </>
    );
}

PlatformOrganization.layout = {
    breadcrumbs: [
        { title: 'Platform admin', href: '/platform' },
        { title: 'Organization', href: '#' },
    ],
};
