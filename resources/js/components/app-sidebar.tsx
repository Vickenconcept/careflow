import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    ClipboardList,
    FolderHeart,
    LayoutGrid,
    MessageSquareText,
    Network,
    Search,
    Settings2,
    Shield,
    Stethoscope,
    Workflow,
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

    const homeGroups: NavGroup[] = [
        {
            title: 'Home',
            items: [
                {
                    title: 'Dashboard',
                    href: dashboardUrl,
                    icon: LayoutGrid,
                },
                ...(teamSlug
                    ? [
                          {
                              title: 'Cases',
                              href: `/${teamSlug}/cases`,
                              icon: FolderHeart,
                          },
                          {
                              title: 'Clinical',
                              icon: Stethoscope,
                              children: [
                                  {
                                      title: 'Doctor queue',
                                      href: `/${teamSlug}/doctor-queue`,
                                      icon: ClipboardList,
                                  },
                                  {
                                      title: 'Workflows',
                                      href: `/${teamSlug}/workflows`,
                                      icon: Workflow,
                                  },
                              ],
                          },
                          {
                              title: 'Communications',
                              icon: MessageSquareText,
                              children: [
                                  {
                                      title: 'Message templates',
                                      href: `/${teamSlug}/organization/message-templates`,
                                      icon: MessageSquareText,
                                  },
                              ],
                          },
                      ]
                    : []),
            ],
        },
    ];

    const workspaceGroups: NavGroup[] = [
        {
            title: 'Workspace',
            items: [
                ...(teamSlug
                    ? [
                          {
                              title: 'Departments',
                              href: `/${teamSlug}/organization/departments`,
                              icon: Network,
                          },
                          {
                              title: 'Organization',
                              href: `/${teamSlug}/organization/settings`,
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
                    title: 'Settings',
                    href: '/settings/profile',
                    icon: Settings2,
                },
            ],
        },
    ];

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
                    <label className="relative block">
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground"
                            strokeWidth={1.5}
                        />
                        <input
                            type="search"
                            placeholder="Search cases..."
                            className="h-9 w-full rounded-xl border border-sidebar-border/80 bg-background/80 pr-14 pl-9 text-[13px] outline-none ring-brand/25 placeholder:text-muted-foreground focus:ring-2"
                        />
                        <kbd className="pointer-events-none absolute top-1/2 right-2 -translate-y-1/2 rounded-md border border-border bg-muted/60 px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
                            Ctrl K
                        </kbd>
                    </label>
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
