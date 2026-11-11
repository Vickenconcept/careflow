import { Head, router, useForm } from '@inertiajs/react';
import { BookOpenText, Bot, Lock, Power, ShieldCheck, Sparkles, Zap } from 'lucide-react';
import InputError from '@/components/input-error';
import { SettingToggle } from '@/components/setting-toggle';
import {
    AssistantAvatar,
    AssistantChat,
    ClearChatButton,
} from '@/components/assistant/assistant-chat';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAssistant, type AssistantState } from '@/hooks/use-assistant';
import { dashboard } from '@/routes';

type Capability = { name: string; label: string; needs_approval: boolean };

type Props = {
    team: { id: number; name: string; slug: string };
    assistant: {
        enabled: boolean;
        name: string;
        knowledge: string | null;
        canManage: boolean;
        capabilities: Capability[];
    };
    chat: AssistantState;
};

function SettingsCard({
    team,
    assistant,
}: {
    team: Props['team'];
    assistant: Props['assistant'];
}) {
    const form = useForm({
        assistant_name: assistant.name,
        assistant_knowledge: assistant.knowledge ?? '',
    });

    return (
        <section className="rounded-2xl border border-border bg-card p-5">
            <h2 className="text-sm font-semibold">Name and hospital knowledge</h2>
            <p className="mt-1 text-xs text-muted-foreground">
                Only admins see this. Changes apply to everyone in {team.name}.
            </p>
            <form
                className="mt-4 space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.patch(`/${team.slug}/assistant/settings`, {
                        preserveScroll: true,
                    });
                }}
            >
                <div className="grid gap-1.5">
                    <Label htmlFor="assistant_name">Name</Label>
                    <Input
                        id="assistant_name"
                        maxLength={40}
                        value={form.data.assistant_name}
                        onChange={(event) => form.setData('assistant_name', event.target.value)}
                    />
                    <InputError message={form.errors.assistant_name} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="assistant_knowledge">Hospital knowledge</Label>
                    <textarea
                        id="assistant_knowledge"
                        rows={6}
                        maxLength={6000}
                        value={form.data.assistant_knowledge}
                        onChange={(event) =>
                            form.setData('assistant_knowledge', event.target.value)
                        }
                        placeholder="Opening hours, airport pickup rules, which department handles what, price list notes, escalation contacts…"
                        className="rounded-xl border border-input bg-transparent px-3 py-2 text-sm outline-none focus:border-brand/50 focus:ring-2 focus:ring-brand/15"
                    />
                    <p className="text-xs text-muted-foreground">
                        Shared with the assistant for everyone in this hospital.
                        Do not put patient details here.
                    </p>
                    <InputError message={form.errors.assistant_knowledge} />
                </div>
                <Button type="submit" disabled={form.processing || !form.isDirty} className="w-full">
                    Save settings
                </Button>
            </form>
        </section>
    );
}

export default function AssistantPage({ team, assistant, chat }: Props) {
    const conversation = useAssistant(team.slug, chat);
    const readTools = assistant.capabilities.filter((tool) => !tool.needs_approval);
    const actionTools = assistant.capabilities.filter((tool) => tool.needs_approval);

    return (
        <>
            <Head title="Command center" />
            <div className="grid gap-5 p-4 md:p-6 xl:h-full xl:grid-cols-[minmax(0,1fr)_320px] xl:overflow-hidden">
                <section className="flex h-[calc(100dvh-8.5rem)] min-h-[540px] flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-sm xl:h-full xl:min-h-0">
                    <header className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4">
                        <div className="flex items-center gap-3">
                            <AssistantAvatar className="size-12" online={assistant.enabled} />
                            <div>
                                <h1 className="text-lg font-semibold tracking-tight">
                                    {assistant.name}
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    Operations assistant · {team.name}
                                </p>
                            </div>
                        </div>
                        {assistant.enabled ? (
                            <ClearChatButton
                                onClear={() => void conversation.clear()}
                                disabled={conversation.busy || conversation.messages.length === 0}
                            />
                        ) : null}
                    </header>

                    {assistant.enabled ? (
                        <AssistantChat
                            slug={team.slug}
                            name={assistant.name}
                            assistant={conversation}
                        />
                    ) : (
                        <div className="flex flex-1 flex-col items-center justify-center gap-3 bg-muted/40 p-8 text-center">
                            <span className="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                <Power className="size-5" />
                            </span>
                            <p className="font-semibold">{assistant.name} is switched off</p>
                            <p className="max-w-sm text-sm text-muted-foreground">
                                {assistant.canManage
                                    ? 'Use the switch at the top of the right-hand panel once your hospital has approved sending case details to the AI provider.'
                                    : 'Ask your hospital admin to turn on the assistant.'}
                            </p>
                        </div>
                    )}
                </section>

                <aside className="space-y-4 xl:min-h-0 xl:overflow-y-auto xl:overscroll-contain xl:pr-1">
                    {assistant.canManage ? (
                        <SettingToggle
                            id="assistant_enabled"
                            icon={Bot}
                            title={`AI assistant (${assistant.name})`}
                            description="Questions and the case details needed to answer them go to the AI provider. Switch on once your hospital has approved that."
                            checked={assistant.enabled}
                            onChange={(value) =>
                                router.patch(
                                    `/${team.slug}/organization/ai`,
                                    { assistant_enabled: value },
                                    { preserveScroll: true },
                                )
                            }
                        />
                    ) : null}

                    <section className="rounded-2xl border border-border bg-card p-5">
                        <div className="flex items-center gap-2">
                            <ShieldCheck className="size-4 text-brand" />
                            <h2 className="text-sm font-semibold">How approvals work</h2>
                        </div>
                        <p className="mt-2 text-xs leading-relaxed text-muted-foreground">
                            {assistant.name} reads freely but never changes
                            anything on its own. Every action appears as a card
                            in the chat, and it only runs after you press
                            Approve.
                        </p>
                    </section>

                    <section className="rounded-2xl border border-border bg-card p-5">
                        <div className="flex items-center gap-2">
                            <Sparkles className="size-4 text-brand" />
                            <h2 className="text-sm font-semibold">What I can do for you</h2>
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Based on your role. You only ever see what you
                            already have access to.
                        </p>
                        <ul className="mt-3 space-y-1.5">
                            {readTools.map((tool) => (
                                <li key={tool.name} className="flex items-center gap-2 text-sm">
                                    <BookOpenText className="size-3.5 text-muted-foreground" />
                                    {tool.label}
                                </li>
                            ))}
                        </ul>
                        {actionTools.length > 0 ? (
                            <>
                                <p className="mt-4 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                                    With your approval
                                </p>
                                <ul className="mt-2 space-y-1.5">
                                    {actionTools.map((tool) => (
                                        <li key={tool.name} className="flex items-center gap-2 text-sm">
                                            <Zap className="size-3.5 text-amber-600" />
                                            {tool.label}
                                            <Lock className="ml-auto size-3 text-muted-foreground" />
                                        </li>
                                    ))}
                                </ul>
                            </>
                        ) : null}
                    </section>

                    {assistant.canManage ? <SettingsCard team={team} assistant={assistant} /> : null}

                    <section className="rounded-2xl border border-border bg-card p-5">
                        <h2 className="text-sm font-semibold">Your memory</h2>
                        <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                            {assistant.name} remembers your own conversation in
                            this hospital, on this page and in the chat bubble on
                            every other page. Other staff cannot see it. Clear
                            the chat to start fresh.
                        </p>
                    </section>
                </aside>
            </div>
        </>
    );
}

AssistantPage.layout = (props: { team: { slug: string } }) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard(props.team.slug) },
        { title: 'Command center', href: `/${props.team.slug}/assistant` },
    ],
});
