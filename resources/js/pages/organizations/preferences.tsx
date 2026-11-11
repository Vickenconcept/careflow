import { Head, router, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import OrganizationTabs from '@/components/organization-tabs';
import { ToggleField } from '@/components/toggle-field';

type Toggle = {
    key: string;
    label: string;
    enabled: boolean;
    locked?: boolean;
};

type Workflow = {
    id: string;
    name: string;
    description: string | null;
    trigger: string | null;
    is_enabled: boolean;
};

type Props = {
    team: { id: number; name: string; slug: string };
    modules: Toggle[];
    notifications: Toggle[];
    workflows: Workflow[];
};

function Panel({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <section className="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div className="border-b border-slate-100 px-5 py-4">
                <h2 className="text-sm font-semibold">{title}</h2>
                <p className="text-xs text-slate-500">{description}</p>
            </div>
            <div className="divide-y divide-slate-100">{children}</div>
        </section>
    );
}

function CheckRow({
    id,
    label,
    hint,
    checked,
    disabled = false,
    onChange,
}: {
    id: string;
    label: string;
    hint?: string | null;
    checked: boolean;
    disabled?: boolean;
    onChange: (checked: boolean) => void;
}) {
    return (
        <ToggleField
            id={id}
            label={label}
            description={hint}
            checked={checked}
            disabled={disabled}
            onChange={onChange}
            boxed={false}
            className="px-5 py-3.5 hover:bg-muted/40"
        />
    );
}

export default function Preferences({
    team,
    modules,
    notifications,
    workflows,
}: Props) {
    const form = useForm({
        modules: Object.fromEntries(
            modules.map((item) => [item.key, item.enabled]),
        ) as Record<string, boolean>,
        notifications: Object.fromEntries(
            notifications.map((item) => [item.key, item.enabled]),
        ) as Record<string, boolean>,
    });

    return (
        <>
            <Head title="Modules and notifications" />
            <OrganizationTabs />
            <div className="space-y-5 p-4 md:p-6">
                <header className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Modules and notifications
                    </h1>
                    <p className="text-sm text-slate-500">
                        Choose which parts of CareFlow your hospital uses and
                        which emails are sent. Turning a module off hides it
                        from staff and patients and blocks its actions.
                    </p>
                </header>

                <form
                    className="space-y-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch(
                            `/${team.slug}/organization/preferences`,
                            { preserveScroll: true },
                        );
                    }}
                >
                    <Panel
                        title="Modules"
                        description="Intake, case management, documents and clinical review are always on."
                    >
                        {modules.map((item) => (
                            <CheckRow
                                key={item.key}
                                id={`module-${item.key}`}
                                label={item.label}
                                hint={
                                    item.locked
                                        ? 'Not included in your plan. Contact the platform administrator.'
                                        : null
                                }
                                disabled={item.locked}
                                checked={form.data.modules[item.key]}
                                onChange={(checked) =>
                                    form.setData('modules', {
                                        ...form.data.modules,
                                        [item.key]: checked,
                                    })
                                }
                            />
                        ))}
                    </Panel>

                    <Panel
                        title="Notifications"
                        description="Emails sent by CareFlow on behalf of your hospital."
                    >
                        {notifications.map((item) => (
                            <CheckRow
                                key={item.key}
                                id={`notification-${item.key}`}
                                label={item.label}
                                checked={form.data.notifications[item.key]}
                                onChange={(checked) =>
                                    form.setData('notifications', {
                                        ...form.data.notifications,
                                        [item.key]: checked,
                                    })
                                }
                            />
                        ))}
                    </Panel>

                    <Button
                        type="submit"
                        disabled={form.processing || !form.isDirty}
                        className="rounded-full bg-brand text-white hover:bg-brand/90"
                    >
                        Save preferences
                    </Button>
                </form>

                <Panel
                    title="Automations"
                    description="Workflows that create tasks and notify staff automatically. Changes apply immediately."
                >
                    {workflows.length === 0 ? (
                        <p className="px-5 py-4 text-sm text-slate-500">
                            No workflows are configured yet.
                        </p>
                    ) : (
                        workflows.map((workflow) => (
                            <CheckRow
                                key={workflow.id}
                                id={`workflow-${workflow.id}`}
                                label={workflow.name}
                                hint={workflow.description}
                                checked={workflow.is_enabled}
                                onChange={(checked) =>
                                    router.patch(
                                        `/${team.slug}/organization/preferences/workflows/${workflow.id}`,
                                        { is_enabled: checked },
                                        { preserveScroll: true },
                                    )
                                }
                            />
                        ))
                    )}
                </Panel>
            </div>
        </>
    );
}
