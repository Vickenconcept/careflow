import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import {
    Building2,
    ClipboardList,
    Contact,
    FolderHeart,
    LayoutGrid,
    ListChecks,
    Search,
    Settings2,
    Route,
    Shield,
    Sparkles,
    Stethoscope,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { TeamSwitcher } from '@/components/team-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarTrigger,
    useSidebar,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavGroup } from '@/types';

export function AppSidebar() {
    const page = usePage();
    const { state } = useSidebar();
    const teamSlug = page.props.currentTeam?.slug;
    const isPlatformAdmin = Boolean(page.props.auth.user?.is_platform_admin);
    const dashboardUrl = teamSlug ? dashboard(teamSlug) : '/';
    const isExpanded = state === 'expanded';

    const role = page.props.currentTeam?.role;
    const isClinical = role === 'doctor' || role === 'nurse';
    const isManager = role === 'owner' || role === 'admin';
    const isViewerOnly = role === 'management_viewer' || role === 'member';

    const homeItems: NavGroup['items'] = [];

    if (!isClinical) {
        homeItems.push({
            title: 'Dashboard',
            href: dashboardUrl,
            icon: LayoutGrid,
        });
    }

    if (teamSlug && isClinical) {
        homeItems.push({
            title: 'My review queue',
            href: `/${teamSlug}/doctor-queue`,
            icon: ClipboardList,
        });
    }

    if (teamSlug) {
        homeItems.push({
            title: 'Command center',
            href: `/${teamSlug}/assistant`,
            icon: Sparkles,
        });
    }

    if (teamSlug && !isViewerOnly) {
        homeItems.push({
            title: 'My work',
            href: `/${teamSlug}/work`,
            icon: ListChecks,
        });
        homeItems.push({
            title: isClinical ? 'All cases' : 'Cases',
            href: `/${teamSlug}/cases`,
            icon: FolderHeart,
        });
    }

    if (teamSlug && !isViewerOnly) {
        homeItems.push({
            title: 'Patients',
            href: `/${teamSlug}/patients`,
            icon: Contact,
        });
    }

    if (teamSlug && (isManager || role === 'coordinator')) {
        homeItems.push({
            title: 'Coordination',
            href: `/${teamSlug}/coordination`,
            icon: Route,
        });
    }

    if (teamSlug && isManager) {
        homeItems.push({
            title: 'Doctor queue',
            href: `/${teamSlug}/doctor-queue`,
            icon: Stethoscope,
        });
    }

    const homeGroups: NavGroup[] = [{ title: 'Home', items: homeItems }];

    const workspaceGroups: NavGroup[] = [
        {
            title: 'Workspace',
            items: [
                ...(teamSlug && (isManager || role === 'coordinator')
                    ? [
                          {
                              title: 'Organization',
                              href: isManager
                                  ? `/${teamSlug}/organization/settings`
                                  : `/${teamSlug}/organization/message-templates`,
                              icon: Building2,
                          },
                      ]
                    : []),
                ...(isPlatformAdmin
                    ? [
                          {
                              title: 'Platform admin',
                              href: '/platform',
                              icon: Shield,
                          },
                      ]
                    : []),
                {
                    title: 'My settings',
                    href: '/settings/profile',
                    icon: Settings2,
                },
            ],
        },
    ];

    const [searchTerm, setSearchTerm] = useState('');
    const canSearchCases = Boolean(teamSlug) && !isViewerOnly;

    const submitSearch = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (!teamSlug || searchTerm.trim() === '') {
            return;
        }

        router.get(`/${teamSlug}/cases`, { search: searchTerm.trim() });
    };

    useEffect(() => {
        if (!canSearchCases) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent): void => {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                document.getElementById('sidebar-case-search')?.focus();
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [canSearchCases]);

    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader className="shrink-0 gap-3 px-3 pt-3 pb-2">
                <div className="flex items-center gap-1">
                    <SidebarMenu className="flex-1">
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                size="lg"
                                asChild
                                className="h-11 rounded-xl hover:bg-transparent"
                            >
                                <Link href={dashboardUrl} prefetch>
                                    <AppLogo />
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                    {isExpanded ? (
                        <SidebarTrigger className="hidden size-8 rounded-lg text-muted-foreground hover:bg-sidebar-accent hover:text-foreground md:flex [&_svg]:size-[18px] [&_svg]:stroke-[1.25]" />
                    ) : null}
                </div>

                <div className="group-data-[collapsible=icon]:hidden">
                    {canSearchCases ? (<form onSubmit={submitSearch} role="search" className="relative block">
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground"
                            strokeWidth={1.5}
                        />
                        <input
                            id="sidebar-case-search"
                            type="search"
                            value={searchTerm}
                            onChange={(event) => setSearchTerm(event.target.value)}
                            aria-label="Search cases"
                            placeholder="Search cases..."
                            className="h-9 w-full rounded-xl border border-sidebar-border/80 bg-background/80 pr-14 pl-9 text-[13px] outline-none ring-brand/25 placeholder:text-muted-foreground focus:ring-2"
                        />
                        <kbd className="pointer-events-none absolute top-1/2 right-2 -translate-y-1/2 rounded-md border border-border bg-muted/60 px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
                            Ctrl K
                        </kbd>
                    </form>) : null}
                </div>
            </SidebarHeader>

            <SidebarContent className="min-h-0 flex-1 gap-1 overflow-y-auto overscroll-contain px-1">
                <NavMain groups={homeGroups} />
            </SidebarContent>

            <SidebarFooter className="shrink-0 gap-1 border-t border-sidebar-border/70 p-2 pt-1">
                <NavMain groups={workspaceGroups} />
                <div className="px-1 pb-1">
                    <TeamSwitcher variant="sidebar-footer" />
                </div>
            </SidebarFooter>
        </Sidebar>
    );
}
