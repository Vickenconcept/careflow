import type { MouseEvent, ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function DataTableCard({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_10px_30px_-24px_rgba(15,23,42,0.45)]',
                className,
            )}
        >
            {children}
        </div>
    );
}

export function DataTable({
    children,
    minWidthClassName = 'min-w-[720px]',
    className,
}: {
    children: ReactNode;
    minWidthClassName?: string;
    className?: string;
}) {
    return (
        <div className="overflow-x-auto">
            <table className={cn('w-full text-sm', minWidthClassName, className)}>
                {children}
            </table>
        </div>
    );
}

export function DataTableHeader({ children }: { children: ReactNode }) {
    return (
        <thead>
            <tr className="border-b border-slate-100 text-left text-xs font-medium tracking-wide text-slate-500 uppercase">
                {children}
            </tr>
        </thead>
    );
}

export function DataTableHead({
    children,
    className,
}: {
    children?: ReactNode;
    className?: string;
}) {
    return (
        <th className={cn('px-4 py-3 font-medium', className)}>{children}</th>
    );
}

export function DataTableBody({ children }: { children: ReactNode }) {
    return <tbody>{children}</tbody>;
}

export function DataTableRow({
    children,
    className,
    onClick,
}: {
    children: ReactNode;
    className?: string;
    onClick?: (event: MouseEvent<HTMLTableRowElement>) => void;
}) {
    return (
        <tr
            onClick={onClick}
            className={cn(
                'border-b border-slate-100 transition-colors last:border-b-0 hover:bg-slate-50/80',
                onClick && 'cursor-pointer',
                className,
            )}
        >
            {children}
        </tr>
    );
}

export function DataTableCell({
    children,
    className,
}: {
    children?: ReactNode;
    className?: string;
}) {
    return (
        <td className={cn('px-4 py-4 align-middle', className)}>{children}</td>
    );
}
