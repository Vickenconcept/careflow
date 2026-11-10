import { IconMoon, IconSun } from '@tabler/icons-react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

export function ThemeToggle({
    className,
}: {
    className?: string;
}) {
    const { resolvedAppearance, toggleAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';

    return (
        <Button
            type="button"
            size="icon"
            variant="outline"
            className={cn('relative', className)}
            aria-label={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
            title={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
            onClick={toggleAppearance}
            data-test="theme-toggle"
        >
            {isDark ? (
                <IconSun className="size-[18px]" stroke={1.25} />
            ) : (
                <IconMoon className="size-[18px]" stroke={1.25} />
            )}
        </Button>
    );
}
