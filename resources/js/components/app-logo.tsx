import { usePage } from '@inertiajs/react';
import { CareflowLogoMark } from '@/components/brand/careflow-logo-mark';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-lg">
                <CareflowLogoMark className="size-8" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate leading-tight font-semibold tracking-tight">
                    {name}
                </span>
            </div>
        </>
    );
}
