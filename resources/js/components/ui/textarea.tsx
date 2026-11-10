import * as React from 'react';

import { cn } from '@/lib/utils';

function Textarea({
    className,
    ...props
}: React.ComponentProps<'textarea'>) {
    return (
        <textarea
            data-slot="textarea"
            className={cn(
                'border-input placeholder:text-muted-foreground flex min-h-28 w-full rounded-xl border bg-white px-4 py-3 text-sm shadow-[0_1px_2px_rgba(15,23,42,0.04),0_4px_12px_rgba(15,23,42,0.04)] transition-[color,box-shadow] outline-none disabled:cursor-not-allowed disabled:opacity-50',
                'focus-visible:border-brand/40 focus-visible:shadow-[0_1px_2px_rgba(47,111,237,0.08),0_0_0_4px_rgba(47,111,237,0.12)] focus-visible:ring-0',
                'aria-invalid:border-destructive aria-invalid:shadow-[0_0_0_4px_rgba(239,68,68,0.12)]',
                'dark:border-border dark:bg-white dark:text-slate-900 dark:placeholder:text-slate-400',
                className,
            )}
            {...props}
        />
    );
}

export { Textarea };
