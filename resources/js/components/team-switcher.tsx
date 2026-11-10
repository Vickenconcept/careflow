import { router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, Plus, Users } from 'lucide-react';
import CreateTeamModal from '@/components/create-team-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useIsMobile } from '@/hooks/use-mobile';
import { switchMethod } from '@/routes/teams';
import type { Team } from '@/types';

type TeamSwitcherProps = {
    inHeader?: boolean;
    variant?: 'default' | 'sidebar-footer';
};

function teamInitials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

export function TeamSwitcher({
    inHeader = false,
    variant = 'default',
}: TeamSwitcherProps) {
    const page = usePage();
    const isMobile = useIsMobile();
    const currentTeam = page.props.currentTeam;
    const teams = page.props.teams ?? [];
    const isFooter = variant === 'sidebar-footer';

    const switchTeam = (team: Team) => {
        const previousTeamSlug = currentTeam?.slug;

        router.visit(switchMethod(team.slug), {
            onFinish: () => {
                if (!previousTeamSlug || typeof window === 'undefined') {
                    router.reload();

                    return;
                }

                const currentUrl = `${window.location.pathname}${window.location.search}${window.location.hash}`;
                const segment = `/${previousTeamSlug}`;

                if (currentUrl.includes(segment)) {
                    router.visit(currentUrl.replace(segment, `/${team.slug}`), {
                        replace: true,
                    });

                    return;
                }

                router.reload();
            },
        });
    };

    if (isFooter) {
        return (
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <button
                        type="button"
                        data-test="team-switcher-trigger"
                        className="flex w-full items-center gap-2.5 rounded-xl border border-sidebar-border/80 bg-background/70 px-2.5 py-2 text-left transition hover:bg-sidebar-accent group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0"
                    >
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand/15 text-[11px] font-semibold text-brand">
                            {teamInitials(currentTeam?.name ?? 'CF')}
                        </span>
                        <div className="min-w-0 flex-1 group-data-[collapsible=icon]:hidden">
                            <div className="truncate text-[13px] font-semibold text-sidebar-foreground">
                                {currentTeam?.name ?? 'Select organization'}
                            </div>
                            <div className="mt-0.5 flex items-center gap-1.5">
                                {currentTeam?.roleLabel ? (
                                    <Badge
                                        variant="secondary"
                                        className="h-5 rounded-md px-1.5 text-[10px] font-medium"
                                    >
                                        {currentTeam.roleLabel}
                                    </Badge>
                                ) : null}
                            </div>
                        </div>
                        <ChevronsUpDown className="size-4 shrink-0 text-muted-foreground group-data-[collapsible=icon]:hidden" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-xl"
                    side={isMobile ? 'top' : 'right'}
                    align="start"
                    sideOffset={8}
                >
                    <DropdownMenuLabel className="text-xs text-muted-foreground">
                        Organizations
                    </DropdownMenuLabel>
                    {teams.map((team) => (
                        <DropdownMenuItem
                            key={team.id}
                            data-test="team-switcher-item"
                            className="cursor-pointer gap-2 p-2"
                            onSelect={() => switchTeam(team)}
                        >
                            <span className="flex size-7 items-center justify-center rounded-md bg-brand/10 text-[10px] font-semibold text-brand">
                                {teamInitials(team.name)}
                            </span>
                            <span className="min-w-0 flex-1 truncate">
                                {team.name}
                            </span>
                            {currentTeam?.id === team.id ? (
                                <Check className="ml-auto size-4" />
                            ) : null}
                        </DropdownMenuItem>
                    ))}
                    <DropdownMenuSeparator />
                    <CreateTeamModal>
                        <DropdownMenuItem
                            data-test="team-switcher-new-team"
                            className="cursor-pointer gap-2 p-2"
                            onSelect={(event) => event.preventDefault()}
                        >
                            <Plus className="size-4" />
                            <span className="text-muted-foreground">
                                New organization
                            </span>
                        </DropdownMenuItem>
                    </CreateTeamModal>
                </DropdownMenuContent>
            </DropdownMenu>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    data-test="team-switcher-trigger"
                    className={
                        inHeader
                            ? 'h-8 gap-1 px-2'
                            : 'w-full justify-start px-2 has-[>svg]:px-2 data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground'
                    }
                >
                    <Users
                        className={
                            inHeader
                                ? 'hidden'
                                : 'hidden size-4 shrink-0 group-data-[collapsible=icon]:block'
                        }
                    />
                    <div
                        className={
                            inHeader
                                ? 'grid flex-1 text-left text-sm leading-tight'
                                : 'grid flex-1 text-left text-sm leading-tight group-data-[collapsible=icon]:hidden'
                        }
                    >
                        <span
                            className={
                                inHeader
                                    ? 'max-w-[120px] truncate font-medium'
                                    : 'truncate font-semibold'
                            }
                        >
                            {currentTeam?.name ?? 'Select team'}
                        </span>
                    </div>
                    <ChevronsUpDown
                        className={
                            inHeader
                                ? 'size-4 opacity-50'
                                : 'ml-auto group-data-[collapsible=icon]:hidden'
                        }
                    />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                className={
                    inHeader
                        ? 'w-56'
                        : 'w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg'
                }
                side={inHeader ? undefined : isMobile ? 'bottom' : 'right'}
                align={inHeader ? 'end' : 'start'}
                sideOffset={inHeader ? undefined : 4}
            >
                <DropdownMenuLabel className="text-xs text-muted-foreground">
                    Teams
                </DropdownMenuLabel>
                {teams.map((team) => (
                    <DropdownMenuItem
                        key={team.id}
                        data-test="team-switcher-item"
                        className={
                            inHeader
                                ? 'cursor-pointer gap-2'
                                : 'cursor-pointer gap-2 p-2'
                        }
                        onSelect={() => switchTeam(team)}
                    >
                        {team.name}
                        {currentTeam?.id === team.id && (
                            <Check
                                className={
                                    inHeader
                                        ? 'ml-auto size-4'
                                        : 'ml-auto h-4 w-4'
                                }
                            />
                        )}
                    </DropdownMenuItem>
                ))}
                <DropdownMenuSeparator />
                <CreateTeamModal>
                    <DropdownMenuItem
                        data-test="team-switcher-new-team"
                        className={
                            inHeader
                                ? 'cursor-pointer gap-2'
                                : 'cursor-pointer gap-2 p-2'
                        }
                        onSelect={(event) => event.preventDefault()}
                    >
                        <Plus className={inHeader ? 'size-4' : 'h-4 w-4'} />
                        <span className="text-muted-foreground">New team</span>
                    </DropdownMenuItem>
                </CreateTeamModal>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
