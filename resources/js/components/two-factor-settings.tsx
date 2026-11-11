import { ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    enabled: boolean;
    required: boolean;
};

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function call<T = unknown>(
    method: 'GET' | 'POST' | 'DELETE',
    url: string,
    body?: Record<string, string>,
): Promise<{ ok: boolean; status: number; data: T | null }> {
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

    let data: T | null = null;

    try {
        data = (await response.json()) as T;
    } catch {
        data = null;
    }

    return { ok: response.ok, status: response.status, data };
}

export default function TwoFactorSettings({ enabled, required }: Props) {
    const [isEnabled, setIsEnabled] = useState(enabled);
    const [setup, setSetup] = useState<{ svg: string; secret: string } | null>(null);
    const [code, setCode] = useState('');
    const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    const expired = 'Your password confirmation expired. Reload this page and confirm your password again.';

    const loadRecoveryCodes = async () => {
        const result = await call<string[]>('GET', '/user/two-factor-recovery-codes');

        if (result.ok && Array.isArray(result.data)) {
            setRecoveryCodes(result.data);
        }
    };

    const start = async () => {
        setBusy(true);
        setError(null);

        const enable = await call('POST', '/user/two-factor-authentication');

        if (!enable.ok) {
            setError(enable.status === 423 ? expired : 'Could not start setup.');
            setBusy(false);

            return;
        }

        const [qr, secret] = await Promise.all([
            call<{ svg: string }>('GET', '/user/two-factor-qr-code'),
            call<{ secretKey: string }>('GET', '/user/two-factor-secret-key'),
        ]);

        if (qr.ok && qr.data && secret.ok && secret.data) {
            setSetup({ svg: qr.data.svg, secret: secret.data.secretKey });
        } else {
            setError('Could not load the setup code.');
        }

        setBusy(false);
    };

    const confirm = async () => {
        setBusy(true);
        setError(null);

        const result = await call('POST', '/user/confirmed-two-factor-authentication', { code });

        if (!result.ok) {
            setError(
                result.status === 423
                    ? expired
                    : 'That code is not valid. Check your authenticator app and try again.',
            );
            setBusy(false);

            return;
        }

        setIsEnabled(true);
        setSetup(null);
        setCode('');
        await loadRecoveryCodes();
        setBusy(false);
    };

    const disable = async () => {
        if (!window.confirm('Turn off two-factor authentication for your account?')) {
            return;
        }

        setBusy(true);
        const result = await call('DELETE', '/user/two-factor-authentication');

        if (result.ok) {
            setIsEnabled(false);
            setRecoveryCodes([]);
        } else {
            setError(result.status === 423 ? expired : 'Could not turn it off.');
        }

        setBusy(false);
    };

    const regenerate = async () => {
        setBusy(true);
        const result = await call('POST', '/user/two-factor-recovery-codes');

        if (result.ok) {
            await loadRecoveryCodes();
        } else {
            setError(result.status === 423 ? expired : 'Could not create new codes.');
        }

        setBusy(false);
    };

    return (
        <section className="space-y-4" aria-labelledby="two-factor-heading">
            <Heading
                variant="small"
                title="Two-factor authentication"
                description="Adds a second step at sign-in using an authenticator app, so a stolen password alone cannot open patient records."
            />

            {required && !isEnabled ? (
                <p className="rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-900 dark:text-amber-100">
                    Your organization requires two-factor authentication. Set it
                    up now to keep using CareFlow.
                </p>
            ) : null}

            {isEnabled ? (
                <div className="space-y-3">
                    <p className="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-700">
                        <ShieldCheck className="size-4" />
                        Two-factor authentication is on
                    </p>

                    {recoveryCodes.length > 0 ? (
                        <div className="space-y-2 rounded-lg border border-border bg-muted/40 p-3">
                            <p className="text-sm font-medium">
                                Recovery codes. Store them somewhere safe; each
                                works once if you lose your phone.
                            </p>
                            <ul className="grid grid-cols-2 gap-1 font-mono text-sm">
                                {recoveryCodes.map((item) => (
                                    <li key={item}>{item}</li>
                                ))}
                            </ul>
                        </div>
                    ) : null}

                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={busy}
                            onClick={recoveryCodes.length ? regenerate : loadRecoveryCodes}
                        >
                            {recoveryCodes.length ? 'Create new recovery codes' : 'Show recovery codes'}
                        </Button>
                        {!required ? (
                            <Button
                                type="button"
                                variant="destructive"
                                disabled={busy}
                                onClick={disable}
                            >
                                Turn off
                            </Button>
                        ) : null}
                    </div>
                </div>
            ) : setup ? (
                <div className="space-y-4">
                    <p className="text-sm">
                        Scan this code with an authenticator app (Google
                        Authenticator, Microsoft Authenticator, 1Password), then
                        enter the 6-digit code it shows.
                    </p>
                    <div
                        className="inline-block rounded-lg border border-border bg-white p-3"
                        dangerouslySetInnerHTML={{ __html: setup.svg }}
                    />
                    <p className="text-xs text-muted-foreground">
                        Can&apos;t scan? Enter this key manually:{' '}
                        <span className="font-mono">{setup.secret}</span>
                    </p>
                    <div className="grid max-w-xs gap-2">
                        <Label htmlFor="two-factor-code">Authentication code</Label>
                        <Input
                            id="two-factor-code"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            maxLength={6}
                            value={code}
                            onChange={(event) => setCode(event.target.value.replace(/\D/g, ''))}
                        />
                    </div>
                    <Button
                        type="button"
                        disabled={busy || code.length !== 6}
                        onClick={confirm}
                    >
                        Confirm and turn on
                    </Button>
                </div>
            ) : (
                <Button type="button" disabled={busy} onClick={start}>
                    Set up two-factor authentication
                </Button>
            )}

            <InputError message={error ?? undefined} />
        </section>
    );
}
