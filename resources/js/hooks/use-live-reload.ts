import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { getEcho } from '@/lib/echo';

export const NOTIFICATION_EVENTS = [
    '.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated',
];

const DEBOUNCE_MS = 250;

/**
 * Refetches the given props when the channel reports a change, and after a reconnect
 * in case events were missed. Events are only hints: the server stays authoritative.
 * Returns whether live updates are currently connected.
 */
export function useLiveReload(
    channel: string,
    events: string[],
    only: string[],
): boolean {
    const [connected, setConnected] = useState(false);
    const onlyRef = useRef(only);

    useEffect(() => {
        const echo = getEcho();

        if (!echo) {
            return;
        }

        let timer: ReturnType<typeof setTimeout> | undefined;
        let wasConnected = false;
        let droppedConnection = false;

        const reload = () => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                // Async, so a background refresh never cancels what the user just did.
                router.reload({ only: onlyRef.current, async: true });
            }, DEBOUNCE_MS);
        };

        const subscription = echo.private(channel);

        events.forEach((event) => {
            subscription.listen(
                event.startsWith('.') ? event : `.${event}`,
                reload,
            );
        });

        const connection = echo.connector.pusher.connection;

        const onChange = ({ current }: { current: string }) => {
            const isConnected = current === 'connected';

            // Events may have been missed while disconnected.
            if (isConnected && droppedConnection) {
                droppedConnection = false;
                reload();
            }

            if (!isConnected && wasConnected) {
                droppedConnection = true;
            }

            wasConnected = isConnected;
            setConnected(isConnected);
        };

        wasConnected = connection.state === 'connected';
        setConnected(wasConnected);
        connection.bind('state_change', onChange);

        return () => {
            clearTimeout(timer);
            connection.unbind('state_change', onChange);
            events.forEach((event) => {
                subscription.stopListening(
                    event.startsWith('.') ? event : `.${event}`,
                );
            });
            echo.leave(channel);
        };
    }, [channel, events]);

    return connected;
}
