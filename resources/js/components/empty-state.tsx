import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    icon: LucideIcon;
    title: string;
    description: string;
    action?: ReactNode;
    className?: string;
};

export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    className,
}: Props) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-14 text-center shadow-[0_10px_30px_-24px_rgba(15,23,42,0.35)]',
                className,
            )}
        >
            <span className="mb-4 flex size-12 items-center justify-center rounded-2xl bg-brand/10 text-brand">
                <Icon className="size-5" strokeWidth={1.5} />
            </span>
            <p className="text-sm font-semibold text-slate-900">{title}</p>
            <p className="mt-1 max-w-sm text-sm text-slate-500">
                {description}
            </p>
            {action ? <div className="mt-5">{action}</div> : null}
        </div>
    );
}
