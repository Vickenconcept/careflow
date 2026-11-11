import { useEffect } from 'react';
import { AppContent } from '@/components/app-content';
import { AssistantWidget } from '@/components/assistant/assistant-widget';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { ImpersonationBanner } from '@/components/impersonation-banner';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    useEffect(() => {
        const html = document.documentElement;
        const body = document.body;
        const previousHtmlOverflow = html.style.overflow;
        const previousBodyOverflow = body.style.overflow;

        html.style.overflow = 'hidden';
        body.style.overflow = 'hidden';

        return () => {
            html.style.overflow = previousHtmlOverflow;
            body.style.overflow = previousBodyOverflow;
        };
    }, []);

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden"
            >
                <div className="shrink-0 bg-background">
                    <ImpersonationBanner />
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                </div>
                <div
                    data-slot="app-main-scroll"
                    className="min-h-0 flex-1 overflow-y-auto overscroll-y-contain bg-background"
                >
                    {children}
                </div>
            </AppContent>
            <AssistantWidget />
        </AppShell>
    );
}
