import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/notification-settings';

type Preference = {
    type: string;
    in_app: boolean;
    email: boolean;
    email_optional: boolean;
};

const labels: Record<string, { title: string; description: string }> = {
    invitation: {
        title: 'Invitations',
        description:
            'Someone invites you to a household or list. Invitation emails always send.',
    },
    reminder_due: {
        title: 'Staple reminders',
        description: 'A staple you may need soon.',
    },
    list_activity: {
        title: 'List changes',
        description: 'Batched updates when others add or remove items.',
    },
    trip_completed: {
        title: 'Finished trips',
        description: 'Someone completes a trip on a list you can open.',
    },
};

export default function NotificationSettings({
    settings,
}: {
    settings: {
        preferences: Preference[];
        quiet_start: string | null;
        quiet_end: string | null;
    };
}) {
    const form = useForm({
        preferences: settings.preferences.map(({ type, in_app, email }) => ({
            type,
            in_app,
            email,
        })),
        quiet_start: settings.quiet_start ?? '',
        quiet_end: settings.quiet_end ?? '',
    });

    const setChannel = (
        index: number,
        channel: 'in_app' | 'email',
        value: boolean,
    ) =>
        form.setData(
            'preferences',
            form.data.preferences.map((preference, position) =>
                position === index
                    ? { ...preference, [channel]: value }
                    : preference,
            ),
        );

    return (
        <>
            <Head title="Notification settings" />

            <h1 className="sr-only">Notification settings</h1>

            <form
                className="space-y-8"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.transform((data) => ({
                        ...data,
                        quiet_start: data.quiet_start || null,
                        quiet_end: data.quiet_end || null,
                    }));
                    form.put(update.url(), { preserveScroll: true });
                }}
            >
                <div className="space-y-4">
                    <Heading
                        variant="small"
                        title="Notifications"
                        description="Choose what reaches you, and where"
                    />
                    <ul className="divide-y rounded-lg border">
                        {settings.preferences.map((preference, index) => {
                            const label = labels[preference.type];

                            return (
                                <li
                                    key={preference.type}
                                    className="flex flex-wrap items-center justify-between gap-3 p-3"
                                >
                                    <span className="min-w-48 flex-1">
                                        {label.title}
                                        <span className="block text-sm text-muted-foreground">
                                            {label.description}
                                        </span>
                                    </span>
                                    <span className="flex gap-4 text-sm">
                                        <label className="flex items-center gap-2">
                                            <Checkbox
                                                checked={
                                                    form.data.preferences[index]
                                                        .in_app
                                                }
                                                onCheckedChange={(checked) =>
                                                    setChannel(
                                                        index,
                                                        'in_app',
                                                        checked === true,
                                                    )
                                                }
                                                aria-label={`${label.title} in app`}
                                            />
                                            In app
                                        </label>
                                        {preference.email_optional && (
                                            <label className="flex items-center gap-2">
                                                <Checkbox
                                                    checked={
                                                        form.data.preferences[
                                                            index
                                                        ].email
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setChannel(
                                                            index,
                                                            'email',
                                                            checked === true,
                                                        )
                                                    }
                                                    aria-label={`${label.title} by email`}
                                                />
                                                Email
                                            </label>
                                        )}
                                    </span>
                                </li>
                            );
                        })}
                    </ul>
                </div>

                <div className="space-y-4">
                    <Heading
                        variant="small"
                        title="Quiet hours"
                        description="No emails or reminders during these hours, in your time zone. Leave both blank to turn this off."
                    />
                    <div className="grid max-w-sm grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="quiet-start">From</Label>
                            <Input
                                id="quiet-start"
                                type="time"
                                value={form.data.quiet_start}
                                onChange={(event) =>
                                    form.setData(
                                        'quiet_start',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.quiet_start} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="quiet-end">Until</Label>
                            <Input
                                id="quiet-end"
                                type="time"
                                value={form.data.quiet_end}
                                onChange={(event) =>
                                    form.setData(
                                        'quiet_end',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.quiet_end} />
                        </div>
                    </div>
                </div>

                <Button type="submit" disabled={form.processing}>
                    Save
                </Button>
            </form>
        </>
    );
}
