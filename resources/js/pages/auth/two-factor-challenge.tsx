import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/two-factor/login';

export default function TwoFactorChallenge() {
    const [useRecovery, setUseRecovery] = useState(false);
    const field = useRecovery ? 'recovery_code' : 'code';

    return (
        <>
            <Head title="Two-factor sign-in" />

            <Form
                key={field}
                {...store.form()}
                resetOnError
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor={field}>
                                {useRecovery
                                    ? 'Recovery code'
                                    : 'Authentication code'}
                            </Label>
                            <Input
                                id={field}
                                name={field}
                                required
                                autoFocus
                                inputMode={useRecovery ? 'text' : 'numeric'}
                                autoComplete={
                                    useRecovery ? 'off' : 'one-time-code'
                                }
                                aria-invalid={errors[field] ? true : undefined}
                                aria-describedby={
                                    errors[field] ? `${field}-error` : undefined
                                }
                            />
                            <InputError
                                id={`${field}-error`}
                                message={errors[field]}
                            />
                        </div>

                        <Button className="w-full" disabled={processing}>
                            {processing && <Spinner />}
                            Continue
                        </Button>

                        <button
                            type="button"
                            className="text-sm text-primary underline-offset-4 hover:underline"
                            onClick={() => setUseRecovery(!useRecovery)}
                        >
                            {useRecovery
                                ? 'Use a code from your authenticator app'
                                : 'Use a recovery code instead'}
                        </button>
                    </>
                )}
            </Form>
        </>
    );
}

TwoFactorChallenge.layout = {
    title: 'Two-factor sign-in',
    description:
        'Enter the code from your authenticator app to finish signing in.',
};
