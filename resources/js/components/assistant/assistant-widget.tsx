import { Link, usePage } from '@inertiajs/react';
import { GripVertical, Maximize2, X } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useRef,
    useState,
    type PointerEvent as ReactPointerEvent,
} from 'react';
import {
    AssistantAvatar,
    AssistantChat,
    ClearChatButton,
} from '@/components/assistant/assistant-chat';
import { useAssistant } from '@/hooks/use-assistant';
import { cn } from '@/lib/utils';

const STORAGE_KEY = 'careflow.assistant.position';
const BUBBLE = 60;
const EDGE = 16;

type Offset = { right: number; bottom: number };

function readOffset(): Offset {
    try {
        const saved = JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? 'null');

        if (saved && typeof saved.right === 'number' && typeof saved.bottom === 'number') {
            return saved as Offset;
        }
    } catch {
        // Storage can be unavailable; fall back to the default corner.
    }

    return { right: 24, bottom: 96 };
}

function clamp(offset: Offset): Offset {
    const maxRight = Math.max(EDGE, window.innerWidth - BUBBLE - EDGE);
    const maxBottom = Math.max(EDGE, window.innerHeight - BUBBLE - EDGE);

    return {
        right: Math.min(Math.max(offset.right, EDGE), maxRight),
        bottom: Math.min(Math.max(offset.bottom, EDGE), maxBottom),
    };
}

function between(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), Math.max(min, max));
}

/**
 * Place the chat panel next to the bubble without covering it: above or below
 * when there is room, otherwise beside it.
 */
function panelPosition(offset: Offset): React.CSSProperties {
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const gap = 12;
    const width = Math.min(400, viewportWidth - EDGE * 2);
    const bubbleLeft = viewportWidth - offset.right - BUBBLE;
    const bubbleTop = viewportHeight - offset.bottom - BUBBLE;
    const above = bubbleTop - gap - EDGE;
    const below = viewportHeight - (bubbleTop + BUBBLE) - gap - EDGE;
    const onLeftHalf = bubbleLeft + BUBBLE / 2 < viewportWidth / 2;

    if (Math.max(above, below) >= 380 || viewportWidth < 640) {
        const useAbove = above >= below;
        const height = Math.min(600, Math.max(useAbove ? above : below, 320));

        return {
            width,
            height,
            left: between(onLeftHalf ? bubbleLeft : bubbleLeft + BUBBLE - width, EDGE, viewportWidth - width - EDGE),
            top: between(useAbove ? bubbleTop - gap - height : bubbleTop + BUBBLE + gap, EDGE, viewportHeight - height - EDGE),
        };
    }

    const height = Math.min(600, viewportHeight - EDGE * 2);

    return {
        width,
        height,
        left: between(onLeftHalf ? bubbleLeft + BUBBLE + gap : bubbleLeft - gap - width, EDGE, viewportWidth - width - EDGE),
        top: between(bubbleTop + BUBBLE / 2 - height / 2, EDGE, viewportHeight - height - EDGE),
    };
}

function Panel({
    slug,
    name,
    offset,
    onClose,
}: {
    slug: string;
    name: string;
    offset: Offset;
    onClose: () => void;
}) {
    const assistant = useAssistant(slug);
    const { loaded, load } = assistant;

    useEffect(() => {
        if (!loaded) {
            void load();
        }
    }, [loaded, load]);

    const style = panelPosition(offset);

    return (
        <div
            role="dialog"
            aria-label={`Chat with ${name}`}
            style={style}
            className="fixed z-50 flex flex-col overflow-hidden rounded-3xl border border-border bg-card shadow-2xl shadow-black/15"
        >
            <header className="flex items-center gap-3 border-b border-border px-4 py-3">
                <AssistantAvatar className="size-10" />
                <div className="min-w-0 flex-1">
                    <p className="truncate font-semibold">{name}</p>
                    <p className="truncate text-xs text-muted-foreground">
                        Same chat as the command center
                    </p>
                </div>
                <Link
                    href={`/${slug}/assistant`}
                    aria-label="Open the command center"
                    className="flex size-8 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                    <Maximize2 className="size-4" />
                </Link>
                <button
                    type="button"
                    onClick={onClose}
                    aria-label="Close chat"
                    className="flex size-8 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                    <X className="size-4" />
                </button>
            </header>
            <div className="flex items-center justify-between border-b border-border px-4 py-1.5">
                <span className="text-xs font-medium text-muted-foreground">Chat</span>
                <ClearChatButton
                    onClear={() => void assistant.clear()}
                    disabled={assistant.busy || assistant.messages.length === 0}
                />
            </div>
            {!loaded && assistant.busy ? (
                <div className="flex flex-1 items-center justify-center text-sm text-muted-foreground">
                    Loading your conversation…
                </div>
            ) : (
                <AssistantChat slug={slug} name={name} assistant={assistant} compact />
            )}
        </div>
    );
}

/**
 * A movable chat bubble available on every page of a hospital workspace.
 * Drag it anywhere; it remembers where you left it.
 */
export function AssistantWidget() {
    const page = usePage();
    const { currentTeam, assistant } = page.props as unknown as {
        currentTeam: { slug: string } | null;
        assistant?: { enabled: boolean; name: string };
    };
    const [open, setOpen] = useState(false);
    const [offset, setOffset] = useState<Offset>({ right: 24, bottom: 96 });
    const drag = useRef<{ x: number; y: number; start: Offset; moved: boolean } | null>(null);

    const restored = useRef(false);

    useEffect(() => {
        setOffset(clamp(readOffset()));
        restored.current = true;

        const onResize = () => setOffset((current) => clamp(current));
        window.addEventListener('resize', onResize);

        return () => window.removeEventListener('resize', onResize);
    }, []);

    useEffect(() => {
        if (!open) {
            return;
        }

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [open]);

    useEffect(() => {
        if (!restored.current) {
            return;
        }

        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(offset));
        } catch {
            // Position is a convenience only.
        }
    }, [offset]);

    const endDrag = useCallback(() => {
        drag.current = null;
    }, []);

    if (!currentTeam || !assistant?.enabled) {
        return null;
    }

    const path = page.url.split('?')[0];

    if (path.startsWith(`/${currentTeam.slug}/assistant`)) {
        return null;
    }

    const onPointerDown = (event: ReactPointerEvent<HTMLDivElement>) => {
        event.currentTarget.setPointerCapture(event.pointerId);
        drag.current = { x: event.clientX, y: event.clientY, start: offset, moved: false };
    };

    const onPointerMove = (event: ReactPointerEvent<HTMLDivElement>) => {
        const state = drag.current;

        if (!state) {
            return;
        }

        const dx = event.clientX - state.x;
        const dy = event.clientY - state.y;

        if (!state.moved && Math.hypot(dx, dy) < 5) {
            return;
        }

        state.moved = true;
        setOffset(clamp({ right: state.start.right - dx, bottom: state.start.bottom - dy }));
    };

    const onPointerUp = () => {
        const state = drag.current;
        drag.current = null;

        if (!state) {
            return;
        }

        if (!state.moved) {
            setOpen((value) => !value);
        }
    };

    return (
        <>
            {open ? (
                <Panel
                    slug={currentTeam.slug}
                    name={assistant.name}
                    offset={offset}
                    onClose={() => setOpen(false)}
                />
            ) : null}
            <div
                role="button"
                tabIndex={0}
                aria-label={open ? `Close chat with ${assistant.name}` : `Chat with ${assistant.name}. Drag to move.`}
                aria-expanded={open}
                onPointerDown={onPointerDown}
                onPointerMove={onPointerMove}
                onPointerUp={onPointerUp}
                onPointerCancel={endDrag}
                onLostPointerCapture={() => {
                    if (drag.current?.moved) {
                        endDrag();
                    }
                }}
                onKeyDown={(event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        setOpen((value) => !value);
                    }
                }}
                style={{ right: offset.right, bottom: offset.bottom, width: BUBBLE, height: BUBBLE }}
                onDragStart={(event) => event.preventDefault()}
                className="group fixed z-50 cursor-grab touch-none select-none active:cursor-grabbing [&_*]:pointer-events-none"
            >
                <span
                    className={cn(
                        'absolute inset-0 rounded-full ring-4 transition',
                        open ? 'ring-brand/40' : 'ring-brand/20 group-hover:ring-brand/35',
                    )}
                />
                {open ? (
                    <span className="flex size-full items-center justify-center rounded-full bg-brand text-brand-foreground shadow-xl">
                        <X className="size-6" />
                    </span>
                ) : (
                    <AssistantAvatar className="size-full shadow-xl" />
                )}
                <span className="absolute top-1/2 -right-4 flex h-8 w-3.5 -translate-y-1/2 items-center justify-center rounded-md bg-foreground/80 text-background opacity-0 transition group-hover:opacity-100">
                    <GripVertical className="size-3" />
                </span>
            </div>
        </>
    );
}
