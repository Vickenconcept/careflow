import * as React from 'react';

import { cn } from '@/lib/utils';

function Input({ className, type, ...props }: React.ComponentProps<'input'>) {
    return (
        <input
            type={type}
            data-slot="input"
            className={cn(
                'border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground flex h-11 w-full min-w-0 rounded-xl border bg-white px-4 py-2 text-sm shadow-[0_1px_2px_rgba(15,23,42,0.04),0_4px_12px_rgba(15,23,42,0.04)] transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
                'focus-visible:border-brand/40 focus-visible:shadow-[0_1px_2px_rgba(95,125,69,0.08),0_0_0_4px_rgba(95,125,69,0.14)] focus-visible:ring-0',
                'aria-invalid:border-destructive aria-invalid:shadow-[0_0_0_4px_rgba(239,68,68,0.12)] dark:aria-invalid:shadow-[0_0_0_4px_rgba(239,68,68,0.2)]',
                'dark:border-border dark:bg-white dark:text-slate-900 dark:placeholder:text-slate-400',
                className,
            )}
            {...props}
        />
    );
}

export { Input };
