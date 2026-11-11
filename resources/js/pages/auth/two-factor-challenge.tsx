import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export default function TwoFactorChallenge() {
    const [useRecovery, setUseRecovery] = useState(false);

    return (
        <>
            <Head title="Two-factor authentication" />

            <Form
                action="/two-factor-challenge"
                method="post"
                key={useRecovery ? 'recovery' : 'code'}
                resetOnError
            >
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor={useRecovery ? 'recovery_code' : 'code'}>
                                {useRecovery ? 'Recovery code' : 'Authentication code'}
                            </Label>
                            {useRecovery ? (
                                <Input
                                    id="recovery_code"
                                    name="recovery_code"
                                    autoComplete="off"
                                    autoFocus
                                />
                            ) : (
                                <Input
                                    id="code"
                                    name="code"
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    maxLength={6}
                                    autoFocus
                                />
                            )}
                            <InputError message={errors.code ?? errors.recovery_code} />
                        </div>

                        <Button
                            className="w-full"
                            disabled={processing}
                            data-test="two-factor-submit"
                        >
                            {processing && <Spinner />}
                            Continue
                        </Button>

                        <button
                            type="button"
                            className="w-full text-center text-sm text-muted-foreground underline"
                            onClick={() => setUseRecovery((value) => !value)}
                        >
                            {useRecovery
                                ? 'Use an authentication code instead'
                                : 'Use a recovery code instead'}
                        </button>
                    </div>
                )}
            </Form>
        </>
    );
}

TwoFactorChallenge.layout = {
    title: 'Two-factor authentication',
    description: 'Enter the code from your authenticator app to finish signing in.',
};
