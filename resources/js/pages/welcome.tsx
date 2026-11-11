import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Bell,
    Building2,
    CalendarCheck,
    Check,
    CheckCircle2,
    ClipboardCheck,
    FileText,
    Globe2,
    HeartPulse,
    Inbox,
    KeyRound,
    Languages,
    LayoutDashboard,
    LockKeyhole,
    Plane,
    ScrollText,
    ServerCog,
    ShieldCheck,
    Sparkles,
    Stethoscope,
    UserRound,
    Users,
    X,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { CareflowLogoMark } from '@/components/brand/careflow-logo-mark';
import { dashboard, login } from '@/routes';

type Props = {
    contactEmail: string | null;
};

const journey = [
    { icon: Inbox, title: 'Enquiry', who: 'Patient', text: 'A branded form in the patient’s language. They get a reference and a private tracking link.' },
    { icon: FileText, title: 'Documents', who: 'AI + team', text: 'Reports are read, translated and structured. Everything stays a draft until people check it.' },
    { icon: ClipboardCheck, title: 'Coordination', who: 'Coordinator', text: 'Assign, correct the AI, sign off translations and see what is missing.' },
    { icon: Stethoscope, title: 'Clinical review', who: 'Doctor', text: 'Doctors see only their cases and approve the clinical recommendation.' },
    { icon: ScrollText, title: 'Proposal', who: 'Coordinator + Doctor', text: 'An approved treatment proposal reaches the patient, who replies from the same link.' },
    { icon: CalendarCheck, title: 'Appointment', who: 'Coordinator', text: 'Propose a slot, the patient confirms, staff add the hospital booking reference.' },
    { icon: Plane, title: 'Travel & stay', who: 'Coordinator', text: 'Arrival, flights, airport pickup and accommodation on the same case.' },
    { icon: HeartPulse, title: 'Follow-up', who: 'Care team', text: 'Post-treatment follow-ups are scheduled and completed, then the case closes.' },
];

const notThis = [
    'An AI document reader',
    'Another hospital management system',
    'A patient registration app',
    'A generic CRM',
    'A chatbot for hospitals',
];

const trust = [
    { icon: KeyRound, title: 'AI only with permission', text: 'Off by default for every hospital. It runs only when the hospital enables it and the patient consents.' },
    { icon: Stethoscope, title: 'AI drafts, clinicians decide', text: 'Extractions and translations are flagged for review. A doctor approves every clinical recommendation.' },
    { icon: ServerCog, title: 'Your storage, if you prefer', text: 'CareFlow’s private storage or your own S3-compatible bucket. Never a public file link.' },
    { icon: Building2, title: 'Every hospital isolated', text: 'Separate staff, cases, settings and storage. One hospital can never see another’s data.' },
    { icon: LockKeyhole, title: 'Two-factor and roles', text: 'Authenticator sign-in, invitation-only accounts and permissions by role.' },
    { icon: ShieldCheck, title: 'Audit, retention, erasure', text: 'Every document opened is logged. Set retention, export a patient’s data, or erase it.' },
];

const languages = [
    { name: 'English', native: 'English' },
    { name: 'Turkish', native: 'Türkçe' },
    { name: 'Arabic', native: 'العربية' },
    { name: 'Russian', native: 'Русский' },
    { name: 'Azerbaijani', native: 'Azərbaycanca' },
];

function Eyebrow({ children, light = false }: { children: ReactNode; light?: boolean }) {
    return (
        <p className={`text-xs font-semibold tracking-[0.2em] uppercase ${light ? 'text-[#b9d09a]' : 'text-brand'}`}>
            {children}
        </p>
    );
}

function StatusPill({ tone, children }: { tone: 'green' | 'amber' | 'blue' | 'slate'; children: ReactNode }) {
    const tones = {
        green: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
        amber: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
        blue: 'bg-[#a8c07f]/15 text-[#4f6e36] dark:text-[#b9d09a]',
        slate: 'bg-slate-500/15 text-slate-600 dark:text-slate-300',
    };

    return <span className={`rounded-full px-2 py-0.5 text-[10px] font-medium whitespace-nowrap ${tones[tone]}`}>{children}</span>;
}

/** A realistic app window that mirrors the real case workspace. */
function AppWindow() {
    const docs = [
        { name: 'Cardiology report.pdf', lang: 'Turkish', tone: 'green' as const, status: 'Reviewed', active: true },
        { name: 'Echo results.jpg', lang: 'Turkish', tone: 'amber' as const, status: 'Needs review', active: false },
        { name: 'Blood tests.pdf', lang: 'English', tone: 'blue' as const, status: 'Processing', active: false },
    ];

    return (
        <div className="overflow-hidden rounded-2xl border border-white/10 bg-[#13241a] shadow-2xl shadow-[#86a05f]/10 ring-1 ring-white/5">
            <div className="flex items-center gap-3 border-b border-white/10 bg-[#0c1a12]/60 px-4 py-2.5">
                <div className="flex gap-1.5">
                    <span className="size-2.5 rounded-full bg-red-400/70" />
                    <span className="size-2.5 rounded-full bg-amber-400/70" />
                    <span className="size-2.5 rounded-full bg-emerald-400/70" />
                </div>
                <div className="mx-auto flex h-6 w-full max-w-xs items-center justify-center gap-1.5 rounded-md bg-white/5 text-[10px] text-slate-400">
                    <LockKeyhole className="size-3" />
                    careflow.app / cases / CF-2026-000148
                </div>
            </div>

            <div className="flex text-slate-200">
                <aside className="hidden w-40 shrink-0 space-y-1 border-r border-white/10 bg-[#0c1a12]/40 p-3 sm:block">
                    <div className="mb-3 flex items-center gap-2 px-2 text-xs font-semibold">
                        <CareflowLogoMark className="size-5" /> CareFlow
                    </div>
                    {[
                        { icon: LayoutDashboard, label: 'Dashboard' },
                        { icon: Stethoscope, label: 'Review queue' },
                        { icon: ClipboardCheck, label: 'My work' },
                        { icon: FileText, label: 'Cases', active: true },
                        { icon: Users, label: 'Patients' },
                        { icon: Building2, label: 'Organization' },
                    ].map((item) => (
                        <div
                            key={item.label}
                            className={`flex items-center gap-2 rounded-lg px-2 py-1.5 text-[11px] ${item.active ? 'bg-[#a8c07f]/15 font-medium text-[#b9d09a]' : 'text-slate-400'}`}
                        >
                            <item.icon className="size-3.5" />
                            {item.label}
                        </div>
                    ))}
                </aside>

                <div className="min-w-0 flex-1 p-4">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <p className="text-[10px] text-slate-400">CF-2026-000148 · Cardiology</p>
                            <p className="text-sm font-semibold text-white">Ahmet Y. · Coronary assessment</p>
                        </div>
                        <div className="flex items-center gap-2">
                            <Bell className="size-4 text-slate-400" />
                            <StatusPill tone="amber">Doctor review</StatusPill>
                        </div>
                    </div>

                    <div className="mt-3 flex gap-4 border-b border-white/10 text-[11px] text-slate-400">
                        {['Overview', 'Documents', 'Evaluation', 'Proposal', 'Travel'].map((tab) => (
                            <span key={tab} className={`pb-2 ${tab === 'Documents' ? 'border-b-2 border-[#a8c07f] font-medium text-white' : ''}`}>
                                {tab}
                            </span>
                        ))}
                    </div>

                    <div className="mt-3 grid gap-3 md:grid-cols-[0.8fr_1.4fr]">
                        <div className="space-y-2">
                            {docs.map((doc) => (
                                <div
                                    key={doc.name}
                                    className={`rounded-lg border p-2.5 ${doc.active ? 'border-[#a8c07f]/40 bg-[#a8c07f]/10' : 'border-white/10 bg-white/[0.02]'}`}
                                >
                                    <p className="truncate text-[11px] font-medium text-white">{doc.name}</p>
                                    <div className="mt-1.5 flex items-center justify-between">
                                        <span className="text-[10px] text-slate-400">{doc.lang}</span>
                                        <StatusPill tone={doc.tone}>{doc.status}</StatusPill>
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="grid grid-cols-2 gap-2 rounded-lg border border-white/10 bg-white/[0.02] p-3">
                            <div>
                                <p className="mb-1.5 flex items-center gap-1 text-[10px] text-slate-400">
                                    <Languages className="size-3" /> Original
                                </p>
                                <p className="text-[10px] leading-4 text-slate-400">
                                    Hastada eforla gelen göğüs ağrısı mevcuttur. EKG’de V4-V6 derivasyonlarında ST depresyonu izlenmiştir.
                                </p>
                            </div>
                            <div className="rounded-md bg-[#a8c07f]/10 p-2">
                                <p className="mb-1.5 flex items-center gap-1 text-[10px] font-medium text-[#b9d09a]">
                                    <Sparkles className="size-3" /> English
                                </p>
                                <p className="text-[10px] leading-4 text-slate-200">
                                    The patient has exertional chest pain. The ECG shows ST depression in leads V4–V6.
                                </p>
                            </div>
                            <div className="col-span-2 mt-1 flex flex-wrap gap-1.5 border-t border-white/10 pt-2">
                                {['Suspected CAD', 'Metformin 1000 mg', 'Penicillin allergy', 'EF 50%'].map((tag) => (
                                    <span key={tag} className="rounded bg-white/5 px-1.5 py-0.5 text-[9px] text-slate-300">
                                        {tag}
                                    </span>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

/** The patient's private tracking page on a phone. */
function PhoneFrame({ className = '' }: { className?: string }) {
    const steps = [
        { label: 'Enquiry received', done: true },
        { label: 'Documents reviewed', done: true },
        { label: 'Doctor opinion', done: true },
        { label: 'Treatment proposal', done: false, current: true },
        { label: 'Appointment & travel', done: false },
    ];

    return (
        <div className={`w-[210px] rounded-[2rem] border-[6px] border-slate-800 bg-white p-3 text-[#13241a] shadow-2xl shadow-black/40 ${className}`}>
            <div className="mx-auto mb-3 h-1 w-12 rounded-full bg-slate-200" />
            <div className="flex items-center gap-2">
                <div className="flex size-7 items-center justify-center rounded-lg bg-brand text-[10px] font-bold text-white">K</div>
                <div>
                    <p className="text-[10px] font-semibold leading-none">Kolan British Hospital</p>
                    <p className="mt-0.5 text-[8px] text-slate-500">International Patients</p>
                </div>
            </div>
            <div className="mt-3 rounded-xl bg-[#eef3e6] p-2.5">
                <p className="text-[8px] text-slate-500">Your reference</p>
                <p className="text-xs font-semibold">CF-2026-000148</p>
                <p className="mt-1.5 text-[9px] font-medium text-[#4f6e36]">Your proposal is ready</p>
            </div>
            <ol className="mt-3 space-y-2">
                {steps.map((step) => (
                    <li key={step.label} className="flex items-center gap-2 text-[9px]">
                        <span
                            className={`flex size-4 items-center justify-center rounded-full ${step.done ? 'bg-emerald-500 text-white' : step.current ? 'bg-brand text-white' : 'bg-slate-200'}`}
                        >
                            {step.done ? <Check className="size-2.5" /> : null}
                        </span>
                        <span className={step.done || step.current ? 'font-medium' : 'text-slate-400'}>{step.label}</span>
                    </li>
                ))}
            </ol>
            <div className="mt-3 rounded-lg bg-brand py-2 text-center text-[10px] font-semibold text-white">View proposal</div>
            <div className="mt-2 flex justify-center gap-1">
                {['EN', 'TR', 'AR', 'RU', 'AZ'].map((code) => (
                    <span key={code} className={`rounded px-1 text-[8px] ${code === 'EN' ? 'bg-[#13241a] text-white' : 'bg-slate-100 text-slate-500'}`}>
                        {code}
                    </span>
                ))}
            </div>
        </div>
    );
}

/** Mini work queue used inside the feature grid. */
function QueueMock() {
    const rows = [
        { ref: 'CF-000151', name: 'Fatima A.', dept: 'IVF', tone: 'blue' as const, status: 'New' },
        { ref: 'CF-000150', name: 'Ivan P.', dept: 'Orthopaedics', tone: 'amber' as const, status: 'Needs review' },
        { ref: 'CF-000148', name: 'Ahmet Y.', dept: 'Cardiology', tone: 'amber' as const, status: 'Doctor review' },
        { ref: 'CF-000146', name: 'Leyla M.', dept: 'Oncology', tone: 'green' as const, status: 'Proposal sent' },
    ];

    return (
        <div className="space-y-2">
            {rows.map((row) => (
                <div key={row.ref} className="flex items-center justify-between gap-3 rounded-xl border border-border bg-background px-3 py-2.5">
                    <div className="flex items-center gap-3">
                        <span className="flex size-8 items-center justify-center rounded-full bg-brand/10 text-xs font-semibold text-brand">
                            {row.name[0]}
                        </span>
                        <div>
                            <p className="text-sm font-medium">{row.name}</p>
                            <p className="text-[11px] text-muted-foreground">
                                {row.ref} · {row.dept}
                            </p>
                        </div>
                    </div>
                    <StatusPill tone={row.tone}>{row.status}</StatusPill>
                </div>
            ))}
        </div>
    );
}

export default function Welcome({ contactEmail }: Props) {
    const { auth, currentTeam } = usePage().props;
    const dashboardUrl = currentTeam ? dashboard(currentTeam.slug) : '/';
    const contactHref = contactEmail
        ? `mailto:${contactEmail}?subject=${encodeURIComponent('CareFlow AI pilot conversation')}`
        : '#pilot';

    return (
        <>
            <Head title="International Patient Operations Platform" />
            <div className="min-h-screen bg-background text-foreground">
                {/* Hero */}
                <section id="top" className="relative overflow-hidden bg-[#0c1a12] text-white">
                    <div aria-hidden className="absolute inset-0 bg-[radial-gradient(60%_50%_at_70%_0%,rgba(111,143,78,0.5),transparent),radial-gradient(40%_40%_at_0%_30%,rgba(168,192,127,0.2),transparent)]" />
                    <div
                        aria-hidden
                        className="absolute inset-0 opacity-[0.07] [background-image:linear-gradient(white_1px,transparent_1px),linear-gradient(90deg,white_1px,transparent_1px)] [background-size:56px_56px] [mask-image:radial-gradient(70%_60%_at_50%_30%,black,transparent)]"
                    />

                    <header className="relative z-20">
                        <div className="mx-auto flex h-20 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6">
                            <a href="#top" className="flex items-center gap-2.5">
                                <CareflowLogoMark className="size-9" />
                                <span className="text-lg font-semibold tracking-tight">
                                    CareFlow <span className="text-[#a8c07f]">AI</span>
                                </span>
                            </a>
                            <nav className="hidden items-center gap-8 text-sm text-slate-300 md:flex">
                                <a href="#platform" className="hover:text-white">Platform</a>
                                <a href="#journey" className="hover:text-white">Journey</a>
                                <a href="#trust" className="hover:text-white">Trust & control</a>
                                <a href="#pilot" className="hover:text-white">Pilot</a>
                            </nav>
                            {auth.user ? (
                                <Link href={dashboardUrl} className="inline-flex items-center gap-1.5 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-[#13241a] hover:bg-white/90">
                                    Open CareFlow <ArrowRight className="size-4" />
                                </Link>
                            ) : (
                                <Link href={login()} className="rounded-lg border border-white/20 px-4 py-2 text-sm font-medium hover:bg-white/10">
                                    Staff sign in
                                </Link>
                            )}
                        </div>
                    </header>

                    <div className="relative z-10 mx-auto max-w-7xl px-4 pt-10 sm:px-6 lg:pt-16">
                        <div className="mx-auto max-w-3xl text-center">
                            <p className="inline-flex items-center gap-2 rounded-full border border-[#a8c07f]/30 bg-[#a8c07f]/10 px-3.5 py-1.5 text-xs font-medium text-[#d3e2bd]">
                                <Globe2 className="size-3.5" />
                                International Patient Operations Platform
                            </p>
                            <h1 className="mt-7 text-4xl font-semibold tracking-tight text-balance sm:text-6xl sm:leading-[1.05]">
                                Every international patient, <span className="bg-gradient-to-r from-[#d3e2bd] to-[#86a05f] bg-clip-text text-transparent">one connected journey.</span>
                            </h1>
                            <p className="mx-auto mt-6 max-w-2xl text-lg leading-8 text-pretty text-slate-300">
                                From the first enquiry and medical documents to clinical review, treatment proposals, travel and follow-up.
                                Built to work alongside your hospital system, not replace it.
                            </p>
                            <div className="mt-9 flex flex-wrap justify-center gap-3">
                                <a href={contactHref} className="inline-flex items-center gap-2 rounded-xl bg-brand px-6 py-3.5 text-sm font-semibold text-brand-foreground shadow-lg shadow-[#5f7d45]/30 hover:opacity-90">
                                    Request a pilot conversation <ArrowRight className="size-4" />
                                </a>
                                <a href="#journey" className="inline-flex items-center rounded-xl border border-white/20 bg-white/5 px-6 py-3.5 text-sm font-semibold hover:bg-white/10">
                                    See the patient journey
                                </a>
                            </div>
                        </div>

                        <div className="relative mx-auto mt-16 max-w-5xl pb-24 lg:pb-32">
                            <AppWindow />
                            <PhoneFrame className="absolute -right-2 -bottom-4 hidden rotate-3 md:block lg:-right-10" />
                            <div className="absolute -bottom-2 -left-2 hidden items-center gap-3 rounded-2xl border border-white/10 bg-[#13241a]/95 p-3.5 shadow-xl backdrop-blur md:flex lg:-left-8">
                                <span className="flex size-9 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-300">
                                    <CheckCircle2 className="size-5" />
                                </span>
                                <div>
                                    <p className="text-xs font-semibold">Translation signed off</p>
                                    <p className="text-[11px] text-slate-400">Turkish → English · by coordinator</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div aria-hidden className="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-background to-transparent" />
                </section>

                {/* Languages */}
                <section className="border-b border-border">
                    <div className="mx-auto flex max-w-6xl flex-col items-center gap-5 px-4 py-10 sm:px-6 md:flex-row md:justify-between">
                        <p className="max-w-xs text-center text-sm text-muted-foreground md:text-left">
                            Patients write, read and reply in their own language.
                        </p>
                        <ul className="flex flex-wrap justify-center gap-2.5">
                            {languages.map((language) => (
                                <li key={language.name} className="rounded-full border border-border bg-card px-4 py-2 text-sm font-medium shadow-sm">
                                    {language.native}
                                </li>
                            ))}
                        </ul>
                    </div>
                </section>

                {/* Problem + positioning */}
                <section className="mx-auto max-w-6xl px-4 py-24 sm:px-6">
                    <div className="mx-auto max-w-2xl text-center">
                        <Eyebrow>The gap</Eyebrow>
                        <h2 className="mt-3 text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                            Your hospital system starts when the patient arrives. The hardest part happens before.
                        </h2>
                        <p className="mt-5 text-base leading-7 text-muted-foreground">
                            Enquiries, foreign-language reports, the doctor’s opinion, the proposal and the flight usually live in inboxes, phones and spreadsheets.
                            CareFlow puts them on one case that the whole team and the patient can follow.
                        </p>
                    </div>

                    <div className="mt-14 grid gap-5 lg:grid-cols-2">
                        <div className="rounded-3xl border border-border bg-card p-8">
                            <Eyebrow>What CareFlow is not</Eyebrow>
                            <ul className="mt-6 space-y-3.5">
                                {notThis.map((item) => (
                                    <li key={item} className="flex items-center gap-3 text-muted-foreground">
                                        <span className="flex size-6 items-center justify-center rounded-full bg-muted">
                                            <X className="size-3.5" />
                                        </span>
                                        {item}
                                    </li>
                                ))}
                            </ul>
                        </div>
                        <div className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#4f6e36] to-[#86a05f] p-8 text-white">
                            <div aria-hidden className="absolute -top-20 -right-20 size-64 rounded-full bg-white/10 blur-2xl" />
                            <p className="text-xs font-semibold tracking-[0.2em] uppercase opacity-80">What CareFlow is</p>
                            <h3 className="relative mt-5 text-2xl font-semibold tracking-tight text-balance sm:text-3xl">
                                The operations layer for your international patient department.
                            </h3>
                            <p className="relative mt-4 leading-7 opacity-90">
                                AI does the slow reading and translating. Your coordinators and doctors stay in charge of every decision, and your hospital system stays your system of record.
                            </p>
                            <ul className="relative mt-6 space-y-2 text-sm">
                                {['Complements your HIS/HBYS', 'One case from enquiry to follow-up', 'Patient sees status without an account'].map((point) => (
                                    <li key={point} className="flex items-center gap-2">
                                        <CheckCircle2 className="size-4" /> {point}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                </section>

                {/* Platform bento */}
                <section id="platform" className="scroll-mt-4 border-y border-border bg-muted/30">
                    <div className="mx-auto max-w-6xl px-4 py-24 sm:px-6">
                        <div className="mx-auto max-w-2xl text-center">
                            <Eyebrow>The platform</Eyebrow>
                            <h2 className="mt-3 text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                                One workspace for every role
                            </h2>
                        </div>

                        <div className="mt-14 grid gap-5 lg:grid-cols-6">
                            <div className="rounded-3xl border border-border bg-card p-7 lg:col-span-4">
                                <div className="flex items-center gap-3">
                                    <span className="flex size-10 items-center justify-center rounded-xl bg-brand/10 text-brand"><Sparkles className="size-5" /></span>
                                    <h3 className="text-lg font-semibold">Documents read, translated and structured</h3>
                                </div>
                                <p className="mt-3 max-w-xl text-sm leading-6 text-muted-foreground">
                                    Reports, scans and photos become searchable English text with diagnoses, medications, allergies and findings pulled out. Staff compare against the original, correct, and sign off.
                                </p>
                                <div className="mt-6 grid gap-3 sm:grid-cols-3">
                                    {[
                                        { title: 'Original', body: 'Hasta, 54 yaş, erkek. Eforla gelen göğüs ağrısı ve nefes darlığı, 3 aydır.', tone: 'border-border bg-background' },
                                        { title: 'English', body: 'Male patient, 54. Chest pain and shortness of breath on exertion for 3 months.', tone: 'border-brand/30 bg-brand/5' },
                                        { title: 'Extracted', body: 'Hypertension · Type 2 diabetes · Penicillin allergy · EF 50%', tone: 'border-border bg-background' },
                                    ].map((card) => (
                                        <div key={card.title} className={`rounded-xl border p-4 ${card.tone}`}>
                                            <p className="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">{card.title}</p>
                                            <p className="mt-2 text-sm leading-5">{card.body}</p>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="flex flex-col items-center justify-between overflow-hidden rounded-3xl border border-border bg-gradient-to-b from-[#eef3e6] to-card p-7 lg:col-span-2 dark:from-[#1c2820]">
                                <div className="self-start">
                                    <div className="flex items-center gap-3">
                                        <span className="flex size-10 items-center justify-center rounded-xl bg-brand/10 text-brand"><UserRound className="size-5" /></span>
                                        <h3 className="text-lg font-semibold">Patients stay informed</h3>
                                    </div>
                                    <p className="mt-3 text-sm leading-6 text-muted-foreground">A private link shows status, proposal and appointments. No account, no app.</p>
                                </div>
                                <PhoneFrame className="mt-6 translate-y-10" />
                            </div>

                            <div className="rounded-3xl border border-border bg-card p-7 lg:col-span-3">
                                <div className="flex items-center gap-3">
                                    <span className="flex size-10 items-center justify-center rounded-xl bg-brand/10 text-brand"><Users className="size-5" /></span>
                                    <h3 className="text-lg font-semibold">A work queue coordinators trust</h3>
                                </div>
                                <p className="mt-3 text-sm leading-6 text-muted-foreground">Saved views, bulk assignment and a clear next step on every case.</p>
                                <div className="mt-5"><QueueMock /></div>
                            </div>

                            <div className="rounded-3xl border border-border bg-card p-7 lg:col-span-3">
                                <div className="flex items-center gap-3">
                                    <span className="flex size-10 items-center justify-center rounded-xl bg-brand/10 text-brand"><Stethoscope className="size-5" /></span>
                                    <h3 className="text-lg font-semibold">Clinical decisions stay clinical</h3>
                                </div>
                                <p className="mt-3 text-sm leading-6 text-muted-foreground">Doctors get a personal review queue with only their cases. Nurses contribute observations; only doctors approve.</p>
                                <div className="mt-5 space-y-2.5">
                                    {[
                                        { icon: ClipboardCheck, text: 'Observation added by nurse', who: 'Nurse', ok: true },
                                        { icon: Stethoscope, text: 'Recommendation approved', who: 'Dr. Demir', ok: true },
                                        { icon: ScrollText, text: 'Treatment proposal drafted', who: 'Coordinator', ok: false },
                                    ].map((event) => (
                                        <div key={event.text} className="flex items-center gap-3 rounded-xl border border-border bg-background px-3 py-2.5">
                                            <span className={`flex size-8 items-center justify-center rounded-full ${event.ok ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300' : 'bg-muted text-muted-foreground'}`}>
                                                <event.icon className="size-4" />
                                            </span>
                                            <div className="flex-1">
                                                <p className="text-sm font-medium">{event.text}</p>
                                                <p className="text-[11px] text-muted-foreground">{event.who}</p>
                                            </div>
                                            {event.ok ? <CheckCircle2 className="size-4 text-emerald-500" /> : null}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Journey */}
                <section id="journey" className="scroll-mt-4 mx-auto max-w-6xl px-4 py-24 sm:px-6">
                    <div className="mx-auto max-w-2xl text-center">
                        <Eyebrow>The journey</Eyebrow>
                        <h2 className="mt-3 text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                            Eight steps, one timeline, nothing lost in between
                        </h2>
                    </div>
                    <ol className="relative mt-16 grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
                        <div aria-hidden className="absolute top-6 right-[12%] left-[12%] hidden h-px bg-gradient-to-r from-transparent via-border to-transparent lg:block" />
                        {journey.map((step, index) => (
                            <li key={step.title} className="relative">
                                <div className="relative z-10 flex size-12 items-center justify-center rounded-2xl border border-border bg-card text-brand shadow-sm">
                                    <step.icon className="size-5" />
                                    <span className="absolute -top-2 -right-2 flex size-5 items-center justify-center rounded-full bg-brand text-[10px] font-bold text-brand-foreground">
                                        {index + 1}
                                    </span>
                                </div>
                                <h3 className="mt-4 font-semibold">{step.title}</h3>
                                <p className="text-xs font-medium text-brand">{step.who}</p>
                                <p className="mt-2 text-sm leading-6 text-muted-foreground">{step.text}</p>
                            </li>
                        ))}
                    </ol>
                </section>

                {/* Trust */}
                <section id="trust" className="relative scroll-mt-4 overflow-hidden bg-[#0c1a12] text-slate-100">
                    <div aria-hidden className="absolute inset-0 bg-[radial-gradient(50%_60%_at_100%_0%,rgba(111,143,78,0.3),transparent)]" />
                    <div className="relative mx-auto max-w-6xl px-4 py-24 sm:px-6">
                        <div className="mx-auto max-w-2xl text-center">
                            <Eyebrow light>Trust & control</Eyebrow>
                            <h2 className="mt-3 text-3xl font-semibold tracking-tight text-balance sm:text-4xl">Built for medical records from day one</h2>
                            <p className="mt-4 leading-7 text-slate-400">The hospital stays in control of its data, its AI use and every clinical decision.</p>
                        </div>
                        <div className="mt-14 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {trust.map((item) => (
                                <div key={item.title} className="rounded-2xl border border-white/10 bg-white/[0.04] p-6 backdrop-blur">
                                    <span className="flex size-10 items-center justify-center rounded-xl bg-[#a8c07f]/10 text-[#b9d09a]">
                                        <item.icon className="size-5" />
                                    </span>
                                    <h3 className="mt-4 font-semibold">{item.title}</h3>
                                    <p className="mt-2 text-sm leading-6 text-slate-400">{item.text}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Audience + pilot */}
                <section id="pilot" className="scroll-mt-4 mx-auto max-w-6xl px-4 py-24 sm:px-6">
                    <div className="mx-auto max-w-3xl text-center">
                        <Eyebrow>Who it’s for</Eyebrow>
                        <h2 className="mt-3 text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                            One platform, configured for each organization
                        </h2>
                        <ul className="mt-8 flex flex-wrap justify-center gap-2.5">
                            {['Private hospitals', 'Fertility & IVF centres', 'Specialist surgical hospitals', 'Medical tourism providers', 'Multi-location clinics'].map((audience) => (
                                <li key={audience} className="rounded-full border border-border bg-card px-4 py-2 text-sm font-medium shadow-sm">
                                    {audience}
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div className="relative mt-16 overflow-hidden rounded-[2rem] bg-gradient-to-br from-[#3f5a2c] via-[#5f7d45] to-[#86a05f] text-white">
                        <div aria-hidden className="absolute -right-24 -bottom-24 size-96 rounded-full bg-white/10 blur-3xl" />
                        <div className="relative grid gap-10 p-8 sm:p-14 lg:grid-cols-[1.1fr_1fr] lg:items-center">
                            <div>
                                <h2 className="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">Start with a controlled pilot.</h2>
                                <p className="mt-4 max-w-lg leading-7 text-white/85">
                                    We begin by understanding how your international patient department works today, then agree a limited pilot with defined users, approved data and clear measures of success.
                                </p>
                                <a href={contactHref} className="mt-8 inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3.5 text-sm font-semibold text-[#13241a] shadow-lg hover:bg-white/90">
                                    Request a pilot conversation <ArrowRight className="size-4" />
                                </a>
                            </div>
                            <ol className="space-y-3">
                                {[
                                    { title: 'Discovery', text: 'Your workflow, systems and pain points.' },
                                    { title: 'Pilot setup', text: 'Departments, staff, languages and integration boundaries.' },
                                    { title: 'Measure', text: 'Response times, review turnaround and patient conversion.' },
                                ].map((step, index) => (
                                    <li key={step.title} className="flex gap-4 rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur">
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-white/20 text-sm font-semibold">{index + 1}</span>
                                        <div>
                                            <p className="font-semibold">{step.title}</p>
                                            <p className="text-sm text-white/80">{step.text}</p>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </div>
                </section>

                <footer className="border-t border-border">
                    <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 py-8 text-sm text-muted-foreground sm:flex-row sm:px-6">
                        <div className="flex items-center gap-2">
                            <CareflowLogoMark className="size-5" />
                            <span>CareFlow AI · International Patient Operations Platform</span>
                        </div>
                        <span>Complements your hospital management system.</span>
                    </div>
                </footer>
            </div>
        </>
    );
}
