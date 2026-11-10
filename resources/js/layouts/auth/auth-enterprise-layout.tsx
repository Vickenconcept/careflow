import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    FileSearch,
    Globe2,
    ShieldCheck,
    Sparkles,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const highlights = [
    {
        icon: Globe2,
        title: 'International patient operations',
        body: 'Enquiry to treatment coordination in one workspace.',
    },
    {
        icon: FileSearch,
        title: 'AI-assisted document intake',
        body: 'Structured summaries with mandatory human review.',
    },
    {
        icon: ShieldCheck,
        title: 'Hospital-grade access control',
        body: 'Organization tenancy, roles, and audit-ready activity.',
    },
];

export default function AuthEnterpriseLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="relative min-h-dvh overflow-hidden bg-background text-foreground">
            <div className="pointer-events-none absolute inset-0">
                <div className="absolute -top-24 left-1/2 h-72 w-[42rem] -translate-x-1/2 rounded-full bg-brand/25 blur-3xl" />
                <div className="absolute bottom-0 right-0 h-80 w-80 rounded-full bg-chart-2/15 blur-3xl" />
            </div>

            <div className="relative mx-auto grid min-h-dvh w-full max-w-7xl lg:grid-cols-[1.05fr_0.95fr]">
                <aside className="relative hidden flex-col justify-between border-r border-border/60 p-10 lg:flex xl:p-14">
                    <Link
                        href={home()}
                        className="inline-flex items-center gap-3"
                    >
                        <span className="flex size-11 items-center justify-center rounded-2xl bg-brand text-brand-foreground enterprise-glow">
                            <AppLogoIcon className="size-6 fill-current" />
                        </span>
                        <span>
                            <span className="font-display block text-lg font-semibold tracking-tight">
                                {name}
                            </span>
                            <span className="text-xs tracking-[0.18em] text-muted-foreground uppercase">
                                Hospital operations platform
                            </span>
                        </span>
                    </Link>

                    <div className="max-w-lg space-y-8">
                        <div className="space-y-4">
                            <div className="inline-flex items-center gap-2 rounded-full border border-brand/30 bg-brand/10 px-3 py-1 text-xs font-medium text-brand">
                                <Sparkles className="size-3.5" />
                                Enterprise clinical coordination
                            </div>
                            <h1 className="font-display text-4xl leading-tight font-semibold tracking-tight text-balance xl:text-5xl">
                                Run international patient flow with confidence.
                            </h1>
                            <p className="text-base text-muted-foreground">
                                CareFlow unifies intake, document review, doctor
                                evaluation, proposals, travel, and follow-up —
                                with AI that assists, never decides alone.
                            </p>
                        </div>

                        <ul className="space-y-4">
                            {highlights.map((item) => (
                                <li
                                    key={item.title}
                                    className="enterprise-panel flex gap-4 p-4"
                                >
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand/15 text-brand">
                                        <item.icon className="size-5" />
                                    </span>
                                    <div>
                                        <p className="font-medium">
                                            {item.title}
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {item.body}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div className="flex items-center gap-3 text-sm text-muted-foreground">
                        <Activity className="size-4 text-brand" />
                        Live operational metrics from your organization data
                    </div>
                </aside>

                <main className="relative flex items-center justify-center p-6 sm:p-10">
                    <div className="w-full max-w-md">
                        <Link
                            href={home()}
                            className="mb-8 inline-flex items-center gap-2 lg:hidden"
                        >
                            <span className="flex size-10 items-center justify-center rounded-xl bg-brand text-brand-foreground">
                                <AppLogoIcon className="size-5 fill-current" />
                            </span>
                            <span className="font-display font-semibold">
                                {name}
                            </span>
                        </Link>

                        <div className="enterprise-panel p-6 sm:p-8">
                            <div className="mb-8 space-y-2">
                                <h2 className="font-display text-2xl font-semibold tracking-tight">
                                    {title}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {description}
                                </p>
                            </div>
                            {children}
                        </div>
                    </div>
                </main>
            </div>
        </div>
    );
}
