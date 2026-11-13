import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

type Channel = {
    name: string;
    socketId: string;
};

function xsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

let instance: Echo<'reverb'> | null | undefined;

/** Null when live updates are not configured, so the app runs without them. */
export function getEcho(): Echo<'reverb'> | null {
    if (instance !== undefined) {
        return instance;
    }

    const key = import.meta.env.VITE_REVERB_APP_KEY as string | undefined;

    if (!key) {
        instance = null;

        return instance;
    }

    (window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;

    const scheme = (import.meta.env.VITE_REVERB_SCHEME as string) ?? 'https';

    instance = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: import.meta.env.VITE_REVERB_HOST as string,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        // The XSRF cookie stays current across sign-in, unlike a token captured at page load.
        authorizer: (channel: { name: string }) => ({
            authorize: (
                socketId: string,
                callback: (error: Error | null, data: never) => void,
            ) => {
                const body: Channel = { name: channel.name, socketId };

                fetch('/broadcasting/auth', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': xsrfToken(),
                    },
                    body: JSON.stringify({
                        channel_name: body.name,
                        socket_id: body.socketId,
                    }),
                })
                    .then(async (response) =>
                        response.ok
                            ? callback(null, (await response.json()) as never)
                            : callback(
                                  new Error(`Status ${response.status}`),
                                  null as never,
                              ),
                    )
                    .catch((error: Error) => callback(error, null as never));
            },
        }),
    });

    return instance;
}
