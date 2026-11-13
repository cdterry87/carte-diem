import { router, useForm } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import twoFactor from '@/routes/two-factor';

export type TwoFactorState = { enabled: boolean; pending: boolean };
export type TwoFactorSetup = { qrSvg: string; secret: string } | null;

const options = { preserveScroll: true } as const;

function RecoveryCodes({ codes }: { codes?: string[] }) {
    if (!codes) {
        return (
            <Button
                variant="outline"
                onClick={() =>
                    router.reload({ only: ['recoveryCodes'], ...options })
                }
            >
                Show recovery codes
            </Button>
        );
    }

    return (
        <div className="space-y-3 rounded-lg border p-4">
            <p className="text-sm text-muted-foreground">
                Keep these somewhere safe, such as a password manager. Each code
                works once, if you lose access to your authenticator app.
            </p>
            <ul
                aria-label="Recovery codes"
                className="grid gap-1 font-mono text-sm sm:grid-cols-2"
            >
                {codes.map((code) => (
                    <li key={code}>{code}</li>
                ))}
            </ul>
            <Button
                variant="outline"
                onClick={() =>
                    router.post(
                        twoFactor.regenerateRecoveryCodes.url(),
                        {},
                        options,
                    )
                }
            >
                Make new codes
            </Button>
        </div>
    );
}

function SetupStep({ setup }: { setup: NonNullable<TwoFactorSetup> }) {
    const form = useForm({ code: '' });

    return (
        <div className="space-y-4 rounded-lg border p-4">
            <p className="text-sm">
                Scan this QR code with an authenticator app, then enter the
                6-digit code it shows.
            </p>
            <div
                role="img"
                aria-label="QR code for your authenticator app"
                className="w-fit rounded-md bg-white p-2 [&_svg]:size-48"
                dangerouslySetInnerHTML={{ __html: setup.qrSvg }}
            />
            <p className="text-sm text-muted-foreground">
                Can&apos;t scan it? Enter this setup key instead:{' '}
                <code className="font-mono break-all text-foreground">
                    {setup.secret}
                </code>
            </p>
            <form
                className="flex flex-wrap items-end gap-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(twoFactor.confirm.url(), {
                        ...options,
                        errorBag: 'confirmTwoFactorAuthentication',
                        onSuccess: () => form.reset(),
                    });
                }}
            >
                <div className="grid gap-2">
                    <Label htmlFor="two-factor-code">Code</Label>
                    <Input
                        id="two-factor-code"
                        value={form.data.code}
                        onChange={(event) =>
                            form.setData('code', event.target.value)
                        }
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        className="w-40"
                        aria-invalid={form.errors.code ? true : undefined}
                        aria-describedby={
                            form.errors.code
                                ? 'two-factor-code-error'
                                : undefined
                        }
                    />
                </div>
                <Button disabled={form.processing}>Confirm</Button>
                <Button
                    type="button"
                    variant="ghost"
                    onClick={() =>
                        router.delete(twoFactor.disable.url(), options)
                    }
                >
                    Cancel
                </Button>
                <InputError
                    id="two-factor-code-error"
                    className="basis-full"
                    message={form.errors.code}
                />
            </form>
        </div>
    );
}

export function TwoFactorSettings({
    state,
    setup,
    recoveryCodes,
}: {
    state: TwoFactorState;
    setup: TwoFactorSetup;
    recoveryCodes?: string[];
}) {
    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <Heading
                    variant="small"
                    title="Two-factor sign-in"
                    description="Optional. When it is on, signing in also asks for a code from an authenticator app."
                />
                <Badge variant={state.enabled ? 'default' : 'outline'}>
                    {state.enabled ? 'On' : 'Off'}
                </Badge>
            </div>

            {state.enabled ? (
                <div className="space-y-4">
                    <RecoveryCodes codes={recoveryCodes} />
                    <ConfirmDialog
                        trigger={
                            <Button variant="destructive">
                                Turn off two-factor sign-in
                            </Button>
                        }
                        title="Turn off two-factor sign-in?"
                        description="Signing in will need only your password. Your recovery codes stop working."
                        confirmLabel="Turn off"
                        destructive
                        onConfirm={() =>
                            router.delete(twoFactor.disable.url(), options)
                        }
                    />
                </div>
            ) : state.pending && setup ? (
                <SetupStep setup={setup} />
            ) : (
                <Button
                    onClick={() =>
                        router.post(twoFactor.enable.url(), {}, options)
                    }
                >
                    <ShieldCheck />
                    Turn on two-factor sign-in
                </Button>
            )}
        </div>
    );
}
