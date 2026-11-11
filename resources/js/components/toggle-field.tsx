import type { ReactNode } from 'react';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    label: string;
    description?: ReactNode;
    checked: boolean;
    onChange: (checked: boolean) => void;
    disabled?: boolean;
    /** Draw the row as its own bordered box (default) or flat inside a list. */
    boxed?: boolean;
    className?: string;
};

/**
 * A compact on/off row for form options: label and hint on the left, a switch
 * on the right. Use it for settings, and keep checkboxes for consents and for
 * picking items from a list.
 */
export function ToggleField({
    id,
    label,
    description,
    checked,
    onChange,
    disabled = false,
    boxed = true,
    className,
}: Props) {
    return (
        <div
            className={cn(
                'flex items-start justify-between gap-4',
                boxed && 'rounded-xl border px-4 py-3 transition-colors',
                boxed && (checked ? 'border-brand/40 bg-brand/5' : 'border-border'),
                disabled && 'opacity-60',
                className,
            )}
        >
            <label htmlFor={id} className={cn('min-w-0', disabled ? 'cursor-not-allowed' : 'cursor-pointer')}>
                <span className="block text-sm font-medium">{label}</span>
                {description ? (
                    <span className="mt-0.5 block text-xs leading-relaxed text-muted-foreground">
                        {description}
                    </span>
                ) : null}
            </label>
            <Switch
                id={id}
                checked={checked}
                disabled={disabled}
                onCheckedChange={onChange}
                aria-label={label}
                className="mt-0.5"
            />
        </div>
    );
}
