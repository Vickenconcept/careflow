import { Link, usePage } from '@inertiajs/react';
import { IconBell, IconChevronDown, IconPlus } from '@tabler/icons-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { ThemeToggle } from '@/components/theme-toggle';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger, useSidebar } from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

const thinIcon = 'size-[18px] stroke-[1.25] text-muted-foreground';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const page = usePage();
    const { auth, currentTeam, pendingWorkCount = 0 } = page.props;
    const pageTitle = pageTitleFor(page.component);
    const { state, isMobile } = useSidebar();
    const getInitials = useInitials();
    const canOpenCases =
        currentTeam && currentTeam.role !== 'management_viewer' && currentTeam.role !== 'member';
    const casesHref = canOpenCases ? `/${currentTeam.slug}/cases` : null;
    const user = auth.user;
    const roleLabel = currentTeam?.roleLabel;
    const showNavbarExpand = isMobile || state === 'collapsed';

    return (
        <header className="z-20 flex shrink-0 items-center gap-3 bg-background px-4 py-3 md:px-6">
            {showNavbarExpand ? (
                <SidebarTrigger
                    className={cn(
                        'size-8 rounded-lg text-muted-foreground hover:bg-muted/70 hover:text-foreground',
                        '[&_svg]:size-[18px] [&_svg]:stroke-[1.25]',
                    )}
                />
            ) : null}

            <div className="min-w-0 flex-1">
                {breadcrumbs.length > 0 ? (
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                ) : (
                    <h1 className="font-display text-lg font-semibold tracking-tight text-foreground">
                        {pageTitle}
                    </h1>
                )}
            </div>

            <div className="ml-auto flex items-center gap-1.5">
                {casesHref ? (
                    <Button
                        asChild
                        size="sm"
                        variant="ghost"
                        className="hidden h-8 gap-1.5 rounded-lg px-2.5 text-muted-foreground hover:bg-muted/70 hover:text-foreground sm:inline-flex"
                    >
                        <Link href={casesHref}>
                            <IconPlus className={thinIcon} />
                            <span className="text-[13px] font-medium">
                                Open cases
                            </span>
                        </Link>
                    </Button>
                ) : null}

                <ThemeToggle className="size-8 rounded-lg border-0 bg-transparent shadow-none hover:bg-muted/70 [&_svg]:size-[18px] [&_svg]:stroke-[1.25] [&_svg]:text-muted-foreground" />

                {casesHref ? (
                    <Button
                        asChild
                        size="icon"
                        variant="ghost"
                        className="relative size-8 rounded-lg text-muted-foreground hover:bg-muted/70 hover:text-foreground"
                    >
                        <Link
                            href={`/${currentTeam?.slug}/work`}
                            aria-label={
                                pendingWorkCount > 0
                                    ? `${pendingWorkCount} open tasks assigned to you. Open my work`
                                    : 'My work. No open tasks'
                            }
                            title={
                                pendingWorkCount > 0
                                    ? `${pendingWorkCount} open tasks assigned to you`
                                    : 'No open tasks assigned to you'
                            }
                        >
                            <IconBell className={thinIcon} />
                            {pendingWorkCount > 0 ? (
                                <span className="absolute top-1.5 right-1.5 size-1.5 rounded-full bg-destructive" />
                            ) : null}
                        </Link>
                    </Button>
                ) : null}

                {user ? (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button
                                type="button"
                                className="flex items-center gap-2 rounded-full py-1 pr-1.5 pl-1 transition hover:bg-muted/60"
                                data-test="navbar-profile-menu"
                            >
                                <span className="flex size-8 items-center justify-center overflow-hidden rounded-full bg-brand/10 text-[11px] font-semibold text-brand">
                                    {user.avatar ? (
                                        <img
                                            src={user.avatar}
                                            alt={user.name}
                                            className="size-full object-cover"
                                        />
                                    ) : (
                                        getInitials(user.name)
                                    )}
                                </span>
                                <span className="hidden min-w-0 text-left sm:block">
                                    <span className="block max-w-[140px] truncate text-xs font-semibold text-foreground">
                                        {user.name}
                                    </span>
                                    <span className="block max-w-[140px] truncate text-[10px] text-muted-foreground">
                                        {roleLabel ??
                                            currentTeam?.name ??
                                            'Account'}
                                    </span>
                                </span>
                                <IconChevronDown
                                    className="hidden size-4 text-muted-foreground sm:block"
                                    stroke={1.25}
                                />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            className="w-56 rounded-xl"
                            align="end"
                            sideOffset={8}
                        >
                            <UserMenuContent user={user} />
                        </DropdownMenuContent>
                    </DropdownMenu>
                ) : null}
            </div>
        </header>
    );
}

const PAGE_TITLES: Record<string, string> = {
    dashboard: 'Dashboard',
    'organizations/work': 'My work',
    'organizations/patients': 'Patients',
    'organizations/coordination': 'Coordination',
    'organizations/audit': 'Audit log',
    'organizations/roles': 'Roles and permissions',
    'organizations/preferences': 'Modules and notifications',
    'organizations/doctor-queue': 'Doctor queue',
    'organizations/workflows/index': 'Workflows',
    'organizations/settings': 'Organization settings',
    'organizations/message-templates/index': 'Message templates',
    'organizations/departments/index': 'Departments',
};

function pageTitleFor(component: string): string {
    if (PAGE_TITLES[component]) {
        return PAGE_TITLES[component];
    }

    const last = component.split('/').pop() ?? 'Dashboard';
    const words = last.replace(/-/g, ' ');

    return words.charAt(0).toUpperCase() + words.slice(1);
}
