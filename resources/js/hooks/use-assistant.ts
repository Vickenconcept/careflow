import { useCallback, useState } from 'react';

export type AssistantAction = { label: string; outcome: 'done' | 'declined' | 'failed' };

export type AssistantMessage = {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    actions: AssistantAction[];
    created_at: string | null;
};

export type AssistantApproval = {
    id: string;
    tool: string;
    label: string;
    summary: string;
};

export type AssistantState = {
    messages: AssistantMessage[];
    pending: AssistantApproval[];
};

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function request(
    method: 'GET' | 'POST' | 'DELETE',
    url: string,
    body?: Record<string, unknown>,
): Promise<AssistantState> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    let data: unknown = null;

    try {
        data = await response.json();
    } catch {
        data = null;
    }

    if (!response.ok) {
        const message =
            (data as { message?: string } | null)?.message ??
            (response.status === 429
                ? 'Too many messages in a short time. Wait a moment and try again.'
                : 'Something went wrong. Please try again.');

        throw new Error(message);
    }

    return data as AssistantState;
}

/**
 * Chat state and actions for the assistant, shared by the command center page
 * and the floating widget. The server keeps the conversation, so both stay in sync.
 */
export function useAssistant(slug: string, initial?: AssistantState) {
    const base = `/${slug}/assistant`;
    const [state, setState] = useState<AssistantState>(
        initial ?? { messages: [], pending: [] },
    );
    const [loaded, setLoaded] = useState(initial !== undefined);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const run = useCallback(async (work: () => Promise<AssistantState>) => {
        setBusy(true);
        setError(null);

        try {
            setState(await work());
            setLoaded(true);
        } catch (exception) {
            setError(
                exception instanceof Error
                    ? exception.message
                    : 'Something went wrong. Please try again.',
            );
        } finally {
            setBusy(false);
        }
    }, []);

    const load = useCallback(
        () => run(() => request('GET', `${base}/state`)),
        [base, run],
    );

    const send = useCallback(
        (text: string) => {
            const message = text.trim();

            if (message === '' || busy) {
                return;
            }

            setState((current) => ({
                ...current,
                messages: [
                    ...current.messages,
                    {
                        id: `local-${Date.now()}`,
                        role: 'user',
                        content: message,
                        actions: [],
                        created_at: new Date().toISOString(),
                    },
                ],
            }));

            void run(() => request('POST', `${base}/messages`, { message }));
        },
        [base, busy, run],
    );

    const decide = useCallback(
        (toolCallId: string, approve: boolean) =>
            run(() =>
                request('POST', `${base}/decisions`, {
                    tool_call_id: toolCallId,
                    approve,
                }),
            ),
        [base, run],
    );

    const clear = useCallback(
        () => run(() => request('DELETE', `${base}/conversation`)),
        [base, run],
    );

    return { ...state, loaded, busy, error, load, send, decide, clear };
}
