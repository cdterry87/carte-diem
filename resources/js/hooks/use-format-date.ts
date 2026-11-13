import { usePage } from '@inertiajs/react';
import { formatDate } from '@/lib/format';

/** Formats dates in the signed-in user's saved time zone. */
export function useFormatDate(): (iso: string | null | undefined) => string {
    const { auth } = usePage().props;

    return (iso) => formatDate(iso, auth.user?.timezone);
}
