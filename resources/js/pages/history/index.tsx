import { Head, Link, router } from '@inertiajs/react';
import { EmptyState } from '@/components/empty-state';
import { ScopeFilter } from '@/components/scope-filter';
import { useFormatDate } from '@/hooks/use-format-date';
import history from '@/routes/history';
import trips from '@/routes/trips';
import type { Trip } from '@/types';

export default function HistoryIndex({
    trips: completed,
    filters,
}: {
    trips: Trip[];
    filters: { scope: string | null };
}) {
    const formatDate = useFormatDate();
    return (
        <>
            <Head title="History" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h1 className="text-xl font-semibold">History</h1>
                    <ScopeFilter
                        value={filters.scope}
                        onChange={(scope) =>
                            router.get(
                                history.index.url(),
                                scope ? { scope } : {},
                                { preserveState: true, replace: true },
                            )
                        }
                    />
                </div>
                {completed.length === 0 ? (
                    <EmptyState title="No completed trips yet">
                        Completed trips show up here.
                    </EmptyState>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {completed.map((trip) => (
                            <li key={trip.uuid}>
                                <Link
                                    href={trips.show(trip.uuid)}
                                    className="flex flex-wrap items-baseline justify-between gap-x-4 p-3 hover:bg-accent"
                                >
                                    <span className="min-w-0 break-words">
                                        {trip.list.title}
                                        <span className="block text-sm text-muted-foreground">
                                            {trip.list.owner?.name}
                                            {trip.store &&
                                                ` · ${trip.store.name}`}
                                        </span>
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {trip.purchased_count} of{' '}
                                        {trip.item_count} bought ·{' '}
                                        {formatDate(trip.completed_at)}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

HistoryIndex.layout = {
    breadcrumbs: [{ title: 'History', href: history.index() }],
};
