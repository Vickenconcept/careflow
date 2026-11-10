import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

export function ImpersonationBanner() {
    const { impersonation, auth } = usePage().props;

    if (!impersonation?.active) {
        return null;
    }

    return (
        <div className="border-b border-amber-500/40 bg-amber-500/15 px-4 py-2 text-sm text-amber-950 dark:text-amber-50">
            <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2">
                <p>
                    Viewing as <strong>{auth.user?.name}</strong>
                    {impersonation.impersonator
                        ? ` (platform admin: ${impersonation.impersonator.email})`
                        : null}
                    . Patient/org access follows this user’s memberships and
                    roles.
                </p>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="border-amber-600/40 bg-background/70"
                    onClick={() =>
                        router.delete('/platform/impersonation', {
                            preserveScroll: true,
                        })
                    }
                >
                    Leave impersonation
                </Button>
            </div>
        </div>
    );
}
