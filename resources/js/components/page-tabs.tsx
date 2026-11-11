import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { cn } from '@/lib/utils';

export type PageTab = {
    id: string;
    label: string;
    icon?: LucideIcon;
    count?: number;
    /** When set the tab navigates; otherwise `onSelect` is called. */
    href?: string;
};

type Props = {
    tabs: PageTab[];
    active: string;
    label: string;
    onSelect?: (id: string) => void;
    /** Used to build element ids so a panel can reference its tab. */
    idPrefix?: string;
    panelId?: string;
    className?: string;
    /** Use 'light' on cards that stay white in dark mode. */
    surface?: 'theme' | 'light';
};

/**
 * One tab bar for the whole app: a soft segmented control with an icon, label
 * and optional count. The active tab lifts onto a card so it is obvious where
 * you are, and the bar scrolls sideways on narrow screens.
 */
export default function PageTabs({
    tabs,
    active,
    label,
    onSelect,
    idPrefix = 'tab',
    panelId,
    className,
    surface = 'theme',
}: Props) {
    const list = useRef<HTMLDivElement>(null);
    const light = surface === 'light';

    useEffect(() => {
        const selected = list.current?.querySelector<HTMLElement>('[aria-selected="true"]');

        selected?.scrollIntoView({ inline: 'center', block: 'nearest' });
    }, [active]);

    return (
        <div
            ref={list}
            role="tablist"
            aria-label={label}
            className={cn(
                'flex gap-1 overflow-x-auto rounded-xl p-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden',
                light ? 'bg-slate-100' : 'bg-muted/70',
                className,
            )}
        >
            {tabs.map((tab) => {
                const isActive = active === tab.id;
                const Icon = tab.icon;
                const classes = cn(
                    'inline-flex shrink-0 items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium whitespace-nowrap transition-all outline-none focus-visible:ring-2 focus-visible:ring-brand/50',
                    isActive
                        ? light
                            ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200'
                            : 'bg-card text-foreground shadow-sm ring-1 ring-border'
                        : light
                          ? 'text-slate-500 hover:bg-white/70 hover:text-slate-900'
                          : 'text-muted-foreground hover:bg-card/60 hover:text-foreground',
                );
                const content = (
                    <>
                        {Icon ? (
                            <Icon
                                className={cn(
                                    'size-4 shrink-0',
                                    isActive ? 'text-brand' : 'opacity-70',
                                )}
                            />
                        ) : null}
                        {tab.label}
                        {tab.count ? (
                            <span
                                className={cn(
                                    'min-w-5 rounded-full px-1.5 py-px text-center text-[11px] font-semibold tabular-nums',
                                    isActive
                                        ? 'bg-brand/10 text-brand'
                                        : light
                                          ? 'bg-white text-slate-600'
                                          : 'bg-background text-muted-foreground',
                                )}
                            >
                                {tab.count}
                            </span>
                        ) : null}
                    </>
                );

                if (tab.href) {
                    return (
                        <Link
                            key={tab.id}
                            href={tab.href}
                            role="tab"
                            aria-selected={isActive}
                            aria-current={isActive ? 'page' : undefined}
                            className={classes}
                        >
                            {content}
                        </Link>
                    );
                }

                return (
                    <button
                        key={tab.id}
                        type="button"
                        role="tab"
                        id={`${idPrefix}-${tab.id}`}
                        aria-selected={isActive}
                        aria-controls={panelId}
                        onClick={() => onSelect?.(tab.id)}
                        className={classes}
                    >
                        {content}
                    </button>
                );
            })}
        </div>
    );
}
