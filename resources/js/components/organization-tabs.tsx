import { usePage } from '@inertiajs/react';
import {
    Building2,
    Cog,
    FileClock,
    HardDrive,
    LayoutTemplate,
    Mail,
    ShieldCheck,
    SlidersHorizontal,
    Users,
    Workflow,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import PageTabs from '@/components/page-tabs';

type Tab = {
    label: string;
    href: string;
    match: string;
    icon: LucideIcon;
    coordinators?: boolean;
};

/**
 * One navigation bar for every organization-administration screen, so the sidebar
 * needs a single "Organization" entry instead of a link per settings page.
 */
export function organizationTabs(slug: string): Tab[] {
    return [
        { label: 'Profile & branding', href: `/${slug}/organization/settings`, match: `/${slug}/organization/settings`, icon: Building2 },
        { label: 'Staff', href: `/settings/teams/${slug}`, match: `/settings/teams/${slug}`, icon: Users },
        { label: 'Departments', href: `/${slug}/organization/departments`, match: `/${slug}/organization/departments`, icon: LayoutTemplate },
        { label: 'Roles', href: `/${slug}/organization/roles`, match: `/${slug}/organization/roles`, icon: ShieldCheck },
        { label: 'Modules & emails', href: `/${slug}/organization/preferences`, match: `/${slug}/organization/preferences`, icon: SlidersHorizontal },
        { label: 'Message templates', href: `/${slug}/organization/message-templates`, match: `/${slug}/organization/message-templates`, icon: Mail, coordinators: true },
        { label: 'Automations', href: `/${slug}/workflows`, match: `/${slug}/workflows`, icon: Workflow, coordinators: true },
        { label: 'Storage', href: `/${slug}/organization/storage`, match: `/${slug}/organization/storage`, icon: HardDrive },
        { label: 'Data & retention', href: `/${slug}/organization/data`, match: `/${slug}/organization/data`, icon: Cog },
        { label: 'Audit log', href: `/${slug}/organization/audit`, match: `/${slug}/organization/audit`, icon: FileClock },
    ];
}

export default function OrganizationTabs() {
    const page = usePage();
    const { currentTeam } = page.props as unknown as {
        currentTeam: { slug: string; role?: string } | null;
    };

    if (!currentTeam) {
        return null;
    }

    const isManager = currentTeam.role === 'owner' || currentTeam.role === 'admin';
    const path = page.url.split('?')[0];
    const tabs = organizationTabs(currentTeam.slug).filter(
        (tab) => isManager || tab.coordinators,
    );

    if (tabs.length < 2) {
        return null;
    }

    const active = tabs.find((tab) => path === tab.match || path.startsWith(`${tab.match}/`));

    return (
        <div className="px-4 pt-4 md:px-6">
            <PageTabs
                label="Organization settings"
                active={active?.href ?? ''}
                tabs={tabs.map((tab) => ({
                    id: tab.href,
                    label: tab.label,
                    icon: tab.icon,
                    href: tab.href,
                }))}
            />
        </div>
    );
}