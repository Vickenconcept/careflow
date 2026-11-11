import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    icon: LucideIcon;
    title: string;
    description: ReactNode;
    checked: boolean;
    onChange: (checked: boolean) => void;
    disabled?: boolean;
    children?: ReactNode;
};

/**
 * A clear on/off row for an important setting: icon, explanation, a status
 * word and a switch, so its state is obvious at a glance.
 */
export function SettingToggle({
    id,
    icon: Icon,
    title,
    description,
    checked,
    onChange,
    disabled = false,
    children,
}: Props) {
    return (
        <div
            className={cn(
                'rounded-2xl border p-4 transition-colors',
                checked ? 'border-brand/40 bg-brand/5' : 'border-border bg-card',
            )}
        >
            <div className="flex items-start gap-4">
                <span
                    className={cn(
                        'flex size-10 shrink-0 items-center justify-center rounded-xl',
                        checked ? 'bg-brand text-brand-foreground' : 'bg-muted text-muted-foreground',
                    )}
                >
                    <Icon className="size-5" />
                </span>
                <div className="min-w-0 flex-1">
                    <label htmlFor={id} className="block cursor-pointer text-sm font-semibold">
                        {title}
                    </label>
                    <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                        {description}
                    </p>
                    {children}
                </div>
                <div className="flex shrink-0 items-center gap-2.5">
                    <span
                        className={cn(
                            'rounded-full px-2 py-0.5 text-[11px] font-semibold',
                            checked
                                ? 'bg-brand/15 text-brand'
                                : 'bg-muted text-muted-foreground',
                        )}
                    >
                        {checked ? 'On' : 'Off'}
                    </span>
                    <Switch
                        id={id}
                        checked={checked}
                        disabled={disabled}
                        onCheckedChange={onChange}
                        aria-label={title}
                    />
                </div>
            </div>
        </div>
    );
}
