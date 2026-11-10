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
    const { auth, currentTeam } = usePage().props;
    const { state, isMobile } = useSidebar();
    const getInitials = useInitials();
    const casesHref = currentTeam ? `/${currentTeam.slug}/cases` : null;
    const user = auth.user;
    const roleLabel = currentTeam?.roleLabel;
    const showNavbarExpand = isMobile || state === 'collapsed';

    return (
        <header className="z-20 flex shrink-0 items-center gap-3 bg-white px-4 py-3 dark:bg-card md:px-6">
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
                        Dashboard
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

                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="relative size-8 rounded-lg text-muted-foreground hover:bg-muted/70 hover:text-foreground"
                    aria-label="Notifications"
                >
                    <IconBell className={thinIcon} />
                    <span className="absolute top-1.5 right-1.5 size-1.5 rounded-full bg-destructive" />
                </Button>

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
