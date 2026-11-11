import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type Tone = 'default' | 'primary' | 'danger';

type RowActionProps = {
    label: string;
    icon: LucideIcon;
    /** Inertia navigation. */
    href?: string;
    /** Plain link (mailto:, tel:, downloads). */
    nativeHref?: string;
    onClick?: () => void;
    tone?: Tone;
    /** Show the label next to the icon: use for the one main action in a row. */
    showLabel?: boolean;
    disabled?: boolean;
};

const toneClasses: Record<Tone, string> = {
    default:
        'border-slate-200 bg-white text-slate-600 hover:border-brand/30 hover:bg-brand/5 hover:text-brand',
    primary: 'border-brand bg-brand text-brand-foreground hover:bg-brand/90',
    danger: 'border-slate-200 bg-white text-slate-600 hover:border-red-300 hover:bg-red-50 hover:text-red-600',
};

/** Right-aligned group of icon actions for the last column of a table row. */
export function RowActions({ children }: { children: ReactNode }) {
    return (
        <div className="flex items-center justify-end gap-1.5">{children}</div>
    );
}

/**
 * A single row action: an icon button with a tooltip, so people can tell what
 * a row lets them do at a glance. The main action can show its label.
 */
export function RowAction({
    label,
    icon: Icon,
    href,
    nativeHref,
    onClick,
    tone = 'default',
    showLabel = false,
    disabled = false,
}: RowActionProps) {
    const className = cn(
        'inline-flex h-8 shrink-0 items-center justify-center gap-1.5 rounded-full border text-xs font-medium transition disabled:pointer-events-none disabled:opacity-40',
        showLabel ? 'px-3' : 'w-8',
        toneClasses[tone],
    );
    const content = (
        <>
            <Icon className="size-4" />
            {showLabel ? label : null}
        </>
    );

    let control: ReactNode;

    if (href && !disabled) {
        control = (
            <Link href={href} className={className} aria-label={label}>
                {content}
            </Link>
        );
    } else if (nativeHref && !disabled) {
        control = (
            <a href={nativeHref} className={className} aria-label={label}>
                {content}
            </a>
        );
    } else {
        control = (
            <button
                type="button"
                className={className}
                aria-label={label}
                disabled={disabled}
                onClick={onClick}
            >
                {content}
            </button>
        );
    }

    if (showLabel) {
        return control;
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>{control}</TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}
