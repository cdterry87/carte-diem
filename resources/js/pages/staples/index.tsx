import { Head, router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { IconButton } from '@/components/icon-button';
import { Button } from '@/components/ui/button';
import { WorkspaceNav } from '@/components/workspace-nav';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useFormatDate } from '@/hooks/use-format-date';
import staples from '@/routes/staples';
import suggestions from '@/routes/suggestions';
import type { Owner, StapleRule } from '@/types';

const options = { preserveScroll: true, preserveState: true };

function StapleRow({ staple }: { staple: StapleRule }) {
    const formatDate = useFormatDate();
    const [cadence, setCadence] = useState(
        staple.cadence_days?.toString() ?? '',
    );
    const update = (data: Record<string, unknown>) =>
        router.patch(staples.update.url(staple.uuid), data as never, options);
    const snoozed =
        staple.snoozed_until !== null &&
        new Date(staple.snoozed_until) > new Date();

    return (
        <li className="grid gap-3 p-3">
            <div className="flex items-center justify-between gap-2">
                <span className="min-w-0 font-medium break-words">
                    {staple.name}
                </span>
                <IconButton
                    label={`Remove ${staple.name} from staples`}
                    variant="ghost"

                    onClick={() =>
                        router.delete(staples.destroy.url(staple.uuid), options)
                    }
                >
                    <Trash2 />
                </IconButton>
            </div>
            <div className="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                <label className="flex items-center gap-2">
                    Every
                    <Input
                        aria-label={`Days between reminders for ${staple.name}`}
                        inputMode="numeric"
                        className="h-9 w-16"
                        placeholder="–"
                        value={cadence}
                        onChange={(event) => setCadence(event.target.value)}
                        onBlur={() => {
                            if (
                                cadence !==
                                (staple.cadence_days?.toString() ?? '')
                            ) {
                                update({
                                    cadence_days:
                                        cadence === '' ? null : Number(cadence),
                                });
                            }
                        }}
                    />
                    days
                </label>
                <label className="flex items-center gap-2">
                    <Checkbox
                        checked={staple.prediction_enabled}
                        onCheckedChange={(checked) =>
                            update({ prediction_enabled: checked === true })
                        }
                    />
                    Predict from purchases
                </label>
                <label className="flex items-center gap-2">
                    <Checkbox
                        checked={staple.reminder_enabled}
                        onCheckedChange={(checked) =>
                            update({ reminder_enabled: checked === true })
                        }
                    />
                    Send reminders
                </label>
                {snoozed ? (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => update({ snooze_days: null })}
                    >
                        Snoozed until {formatDate(staple.snoozed_until)}. Resume
                    </Button>
                ) : (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => update({ snooze_days: 7 })}
                    >
                        Snooze 7 days
                    </Button>
                )}
            </div>
        </li>
    );
}

export default function StaplesIndex({
    owner,
    staples: rules,
    muted,
}: {
    owner: Owner;
    staples: StapleRule[];
    muted: { product: string; name: string }[];
}) {
    const form = useForm({ name: '', cadence_days: '' });

    return (
        <>
            <Head title="Staples" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
                <header className="flex flex-col gap-2">
                    <h1 className="text-xl font-semibold">Staples</h1>
                    <p className="text-sm text-muted-foreground">
                        Items you buy regularly. Reminders say you may need
                        something, because we only know when you last bought it.
                    </p>
                    <WorkspaceNav
                        current={owner.uuid}
                        hrefFor={(query) => staples.index({ query })}
                    />
                </header>

                <form
                    className="grid gap-2 sm:grid-cols-[1fr_8rem_auto]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({
                            name: data.name,
                            cadence_days:
                                data.cadence_days === ''
                                    ? null
                                    : Number(data.cadence_days),
                            owner: { type: owner.type, uuid: owner.uuid },
                        }));
                        form.post(staples.store.url(), {
                            ...options,
                            onSuccess: () => form.reset(),
                        });
                    }}
                >
                    <Input
                        required
                        aria-label="Staple name"
                        placeholder="Staple, like coffee"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                    />
                    <Input
                        aria-label="Remind every how many days (optional)"
                        inputMode="numeric"
                        placeholder="Every __ days"
                        value={form.data.cadence_days}
                        onChange={(event) =>
                            form.setData('cadence_days', event.target.value)
                        }
                    />
                    <Button type="submit" disabled={form.processing}>
                        Add staple
                    </Button>
                    <InputError
                        message={form.errors.name ?? form.errors.cadence_days}
                        className="sm:col-span-3"
                    />
                </form>

                {rules.length === 0 ? (
                    <EmptyState title="No staples yet">
                        Add the things you never want to run out of.
                    </EmptyState>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {rules.map((staple) => (
                            <StapleRow key={staple.uuid} staple={staple} />
                        ))}
                    </ul>
                )}

                {muted.length > 0 && (
                    <section aria-labelledby="muted-heading">
                        <h2 id="muted-heading" className="mb-2 font-medium">
                            Items we no longer suggest
                        </h2>
                        <ul className="divide-y rounded-lg border">
                            {muted.map((item) => (
                                <li
                                    key={item.product}
                                    className="flex items-center justify-between gap-2 p-3"
                                >
                                    <span className="min-w-0 break-words">
                                        {item.name}
                                    </span>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        aria-label={`Suggest again: ${item.name}`}
                                        onClick={() =>
                                            router.delete(
                                                suggestions.lift.url(
                                                    item.product,
                                                ),
                                                options,
                                            )
                                        }
                                    >
                                        Suggest again
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

StaplesIndex.layout = {
    breadcrumbs: [{ title: 'Staples', href: staples.index() }],
};
