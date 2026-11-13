import { Head, Link, router } from '@inertiajs/react';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { useFormatDate } from '@/hooks/use-format-date';
import { cn } from '@/lib/utils';
import notifications from '@/routes/notifications';
import { edit as editSettings } from '@/routes/notification-settings';
import type { AppNotification } from '@/types';

export default function NotificationsIndex({
    notifications: items,
}: {
    notifications: AppNotification[];
}) {
    const formatDate = useFormatDate();
    const hasUnread = items.some((item) => item.read_at === null);

    return (
        <>
            <Head title="Notifications" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold">Notifications</h1>
                    <div className="flex gap-2">
                        {hasUnread && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.post(
                                        notifications.readAll.url(),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Mark all as read
                            </Button>
                        )}
                        <Button asChild variant="outline" size="sm">
                            <Link href={editSettings()}>Settings</Link>
                        </Button>
                    </div>
                </div>
                {items.length === 0 ? (
                    <EmptyState title="Nothing yet">
                        Reminders and household activity show up here.
                    </EmptyState>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {items.map((item) => (
                            <li key={item.id}>
                                <button
                                    type="button"
                                    className="block w-full p-3 text-left hover:bg-accent"
                                    onClick={() =>
                                        router.post(
                                            notifications.open.url(item.id),
                                        )
                                    }
                                >
                                    <span
                                        className={cn(
                                            'block break-words',
                                            item.read_at === null &&
                                                'font-semibold',
                                        )}
                                    >
                                        {item.read_at === null && (
                                            <span className="sr-only">
                                                Unread:{' '}
                                            </span>
                                        )}
                                        {item.title}
                                    </span>
                                    <span className="block text-sm break-words text-muted-foreground">
                                        {item.body}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {formatDate(item.created_at)}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

NotificationsIndex.layout = {
    breadcrumbs: [{ title: 'Notifications', href: notifications.index() }],
};
