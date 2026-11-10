import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import type { NavGroup, NavItem } from '@/types';

function itemIsActive(
    item: NavItem,
    isCurrentUrl: (url: NavItem['href']) => boolean,
    currentUrl: string,
): boolean {
    if (!item.href) {
        return (item.children ?? []).some((child) =>
            itemIsActive(child, isCurrentUrl, currentUrl),
        );
    }

    const href = toUrl(item.href);
    const isCasesRoute =
        /\/cases\/?$/.test(href) || href.endsWith('/cases');

    if (isCasesRoute) {
        return (
            currentUrl === href ||
            currentUrl.startsWith(`${href.replace(/\/$/, '')}/`)
        );
    }

    return isCurrentUrl(item.href);
}

function NavLinkItem({ item }: { item: NavItem }) {
    const { isCurrentUrl, currentUrl } = useCurrentUrl();
    const active = itemIsActive(item, isCurrentUrl, currentUrl);
    const children = item.children ?? [];
    const [open, setOpen] = useState(
        active || children.some((child) => itemIsActive(child, isCurrentUrl, currentUrl)),
    );

    if (children.length > 0) {
        return (
            <Collapsible open={open} onOpenChange={setOpen} asChild>
                <SidebarMenuItem>
                    <CollapsibleTrigger asChild>
                        <SidebarMenuButton
                            tooltip={{ children: item.title }}
                            className={cn(
                                'h-9 rounded-lg text-[13px] font-medium text-sidebar-foreground/75',
                                'hover:bg-sidebar-accent hover:text-sidebar-foreground',
                                open && 'bg-sidebar-accent/70 text-sidebar-foreground',
                            )}
                        >
                            {item.icon ? (
                                <item.icon className="size-4 stroke-[1.5]" />
                            ) : null}
                            <span className="flex-1 text-left">{item.title}</span>
                            <ChevronRight
                                className={cn(
                                    'size-3.5 text-muted-foreground transition-transform',
                                    open && 'rotate-90',
                                )}
                            />
                        </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarMenuSub className="mx-0 border-l border-sidebar-border/80 px-0 ml-4 translate-x-0">
                            {children.map((child) => {
                                const childActive = itemIsActive(
                                    child,
                                    isCurrentUrl,
                                    currentUrl,
                                );

                                return (
                                    <SidebarMenuSubItem key={child.title}>
                                        <SidebarMenuSubButton
                                            asChild
                                            isActive={childActive}
                                            className={cn(
                                                'h-8 rounded-md text-[13px] [&_svg]:text-muted-foreground',
                                                childActive &&
                                                    'bg-brand/10 font-medium text-brand hover:bg-brand/15 hover:text-brand [&_svg]:text-brand',
                                            )}
                                        >
                                            <Link
                                                href={child.href ?? '#'}
                                                prefetch
                                            >
                                                {child.icon ? (
                                                    <child.icon className="size-3.5 stroke-[1.5]" />
                                                ) : null}
                                                <span>{child.title}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                );
                            })}
                        </SidebarMenuSub>
                    </CollapsibleContent>
                </SidebarMenuItem>
            </Collapsible>
        );
    }

    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                asChild
                isActive={active}
                tooltip={{ children: item.title }}
                className={cn(
                    'h-9 rounded-lg text-[13px] font-medium text-sidebar-foreground/75',
                    'hover:bg-sidebar-accent hover:text-sidebar-foreground',
                    'data-[active=true]:bg-brand/10 data-[active=true]:font-semibold data-[active=true]:text-brand',
                    'data-[active=true]:hover:bg-brand/15 data-[active=true]:hover:text-brand',
                    'data-[active=true]:[&_svg]:text-brand',
                )}
            >
                <Link href={item.href ?? '#'} prefetch>
                    {item.icon ? (
                        <item.icon className="size-4 stroke-[1.5]" />
                    ) : null}
                    <span>{item.title}</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    );
}

export function NavMain({ groups }: { groups: NavGroup[] }) {
    return (
        <>
            {groups.map((group) => (
                <SidebarGroup key={group.title} className="px-2 py-2">
                    <SidebarGroupLabel className="mb-1 px-2 text-[11px] font-semibold tracking-[0.14em] text-muted-foreground/80 uppercase">
                        {group.title}
                    </SidebarGroupLabel>
                    <SidebarMenu className="gap-0.5">
                        {group.items.map((item) => (
                            <NavLinkItem key={item.title} item={item} />
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}
