import { cn } from '@/lib/utils';

type SwitchProps = {
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    disabled?: boolean;
    id?: string;
    className?: string;
    'aria-label'?: string;
};

function Switch({
    checked,
    onCheckedChange,
    disabled = false,
    id,
    className,
    'aria-label': ariaLabel,
}: SwitchProps) {
    return (
        <button
            type="button"
            role="switch"
            id={id}
            aria-checked={checked}
            aria-label={ariaLabel}
            disabled={disabled}
            onClick={() => onCheckedChange(!checked)}
            className={cn(
                'inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-brand/35 disabled:cursor-not-allowed disabled:opacity-50',
                checked ? 'bg-brand' : 'bg-slate-300 dark:bg-slate-700',
                className,
            )}
        >
            <span
                className={cn(
                    'pointer-events-none block size-5 rounded-full bg-white shadow-sm ring-0 transition-transform',
                    checked ? 'translate-x-5' : 'translate-x-0',
                )}
            />
        </button>
    );
}

export { Switch };
