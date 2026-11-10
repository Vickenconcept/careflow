import type { ComponentProps } from 'react';
import { CareflowLogoMark } from '@/components/brand/careflow-logo-mark';
import { cn } from '@/lib/utils';

export default function AppLogoIcon({
    className,
}: {
    className?: string;
} & ComponentProps<'span'>) {
    return (
        <span className={cn('inline-flex size-full items-center justify-center', className)}>
            <CareflowLogoMark className="size-full" />
        </span>
    );
}
