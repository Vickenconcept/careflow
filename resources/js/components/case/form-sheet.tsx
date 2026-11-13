import type { ReactNode } from 'react';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    children: ReactNode;
};

/**
 * A side panel for adding or editing a case record, so the page shows the
 * records themselves instead of long empty forms.
 */
export function FormSheet({
    open,
    onOpenChange,
    title,
    description,
    children,
}: Props) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="light-surface w-full gap-0 overflow-y-auto bg-white p-0 sm:max-w-xl">
                <SheetHeader className="border-b border-slate-100 px-6 py-5">
                    <SheetTitle className="text-base font-semibold text-slate-900">
                        {title}
                    </SheetTitle>
                    {description ? (
                        <SheetDescription className="text-sm text-slate-500">
                            {description}
                        </SheetDescription>
                    ) : null}
                </SheetHeader>
                <div className="px-6 py-5">{children}</div>
            </SheetContent>
        </Sheet>
    );
}
