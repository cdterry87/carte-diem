import { Head, Link, router, usePoll } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { TripProgress } from '@/components/trips/trip-progress';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useFormatDate } from '@/hooks/use-format-date';
import { useLiveReload } from '@/hooks/use-live-reload';
import {
    formatAmount,
    formatMoney,
    formatQuantity,
    minorToInput,
    parseMoney,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import listRoutes from '@/routes/lists';
import purchases from '@/routes/purchases';
import trips from '@/routes/trips';
import type { Trip, TripItem } from '@/types';

const TRIP_EVENTS = ['TripItemChecked', 'TripCompleted', 'ListItemAdded'];

function TripRow({
    trip,
    item,
    editable,
}: {
    trip: Trip;
    item: TripItem;
    editable: boolean;
}) {
    const [pending, setPending] = useState<boolean | null>(null);
    const [quantity, setQuantity] = useState(
        formatQuantity(item.actual_quantity),
    );
    const [notes, setNotes] = useState(item.notes ?? '');
    const currency = trip.currency_code ?? 'USD';
    const [price, setPrice] = useState(
        minorToInput(item.line_total_minor, currency),
    );
    const [priceError, setPriceError] = useState<string>();
    const purchased = pending ?? item.purchased;
    const planned = formatAmount(item.planned_quantity, item.planned_unit);
    const url = trips.items.update.url({ trip: trip.uuid, item: item.uuid });
    const options = { preserveScroll: true, preserveState: true };

    return (
        <li className="py-1">
            <label className="flex min-h-14 cursor-pointer items-center gap-3 rounded-md px-1">
                <Checkbox
                    checked={purchased}
                    disabled={!editable}
                    onCheckedChange={(next) => {
                        setPending(next === true);
                        router.patch(
                            url,
                            { purchased: next === true },
                            { ...options, onFinish: () => setPending(null) },
                        );
                    }}
                    aria-label={`${item.name} ${purchased ? 'in the cart' : 'not in the cart'}`}
                    className="size-6"
                />
                <span className="min-w-0 flex-1">
                    <span
                        className={cn(
                            'text-base break-words',
                            purchased && 'text-muted-foreground line-through',
                        )}
                    >
                        {item.name}
                    </span>
                    {planned && (
                        <span className="ml-2 text-sm text-muted-foreground">
                            {planned}
                        </span>
                    )}
                    {item.notes && !(item.purchased && editable) && (
                        <span className="block text-sm break-words text-muted-foreground">
                            {item.notes}
                        </span>
                    )}
                </span>
            </label>
            {/* Waits for the server: the row remounts in the cart section, which would drop typed text. */}
            {item.purchased &&
                pending !== false &&
                (editable || item.actual_quantity !== null) && (
                    <div className="ml-10 flex flex-wrap items-center gap-2 pb-1">
                        <label
                            htmlFor={`bought-${item.uuid}`}
                            className="text-sm text-muted-foreground"
                        >
                            Bought
                        </label>
                        <Input
                            id={`bought-${item.uuid}`}
                            inputMode="decimal"
                            disabled={!editable}
                            className="h-9 w-20"
                            placeholder={formatQuantity(item.planned_quantity)}
                            value={quantity}
                            onChange={(event) =>
                                setQuantity(event.target.value)
                            }
                            onBlur={() => {
                                if (
                                    quantity !==
                                    formatQuantity(item.actual_quantity)
                                ) {
                                    router.patch(
                                        url,
                                        {
                                            actual_quantity:
                                                quantity === ''
                                                    ? null
                                                    : quantity,
                                        },
                                        options,
                                    );
                                }
                            }}
                        />
                        {item.planned_unit && (
                            <span className="text-sm text-muted-foreground">
                                {item.planned_unit}
                            </span>
                        )}
                        {editable && trip.can.manage_amounts && (
                            <Input
                                aria-label={`Price of ${item.name}`}
                                inputMode="decimal"
                                placeholder="Price"
                                className="h-9 w-24"
                                value={price}
                                onChange={(event) =>
                                    setPrice(event.target.value)
                                }
                                onBlur={() => {
                                    const minor = parseMoney(price, currency);

                                    setPriceError(
                                        minor === undefined
                                            ? 'Enter an amount like 12.50.'
                                            : undefined,
                                    );

                                    if (
                                        minor !== undefined &&
                                        minor !==
                                            (item.line_total_minor ?? null)
                                    ) {
                                        router.patch(
                                            url,
                                            { line_total_minor: minor },
                                            options,
                                        );
                                    }
                                }}
                            />
                        )}
                        {editable && (
                            <Input
                                aria-label={`Note for ${item.name}`}
                                placeholder="Add a note"
                                className="h-9 min-w-40 flex-1"
                                value={notes}
                                onChange={(event) =>
                                    setNotes(event.target.value)
                                }
                                onBlur={() => {
                                    if (notes !== (item.notes ?? '')) {
                                        router.patch(
                                            url,
                                            {
                                                notes:
                                                    notes === '' ? null : notes,
                                            },
                                            options,
                                        );
                                    }
                                }}
                            />
                        )}
                        <InputError
                            className="basis-full"
                            message={priceError}
                        />
                    </div>
                )}
        </li>
    );
}

function TripTotal({ trip, items }: { trip: Trip; items: TripItem[] }) {
    const currency = trip.currency_code ?? 'USD';
    const [total, setTotal] = useState(
        minorToInput(trip.total_minor, currency),
    );
    const [error, setError] = useState<string>();
    const pricedSum = items
        .filter((item) => item.purchased)
        .reduce((sum, item) => sum + (item.line_total_minor ?? 0), 0);

    return (
        <section className="grid gap-2 rounded-lg border p-3">
            <label htmlFor="trip-total" className="font-medium">
                Total paid
            </label>
            <Input
                id="trip-total"
                inputMode="decimal"
                className="w-32"
                value={total}
                onChange={(event) => setTotal(event.target.value)}
                onBlur={() => {
                    const minor = parseMoney(total, currency);

                    setError(
                        minor === undefined
                            ? 'Enter an amount like 12.50.'
                            : undefined,
                    );

                    if (
                        minor !== undefined &&
                        minor !== (trip.total_minor ?? null)
                    ) {
                        router.patch(
                            trips.amount.url(trip.uuid),
                            { total_minor: minor },
                            { preserveScroll: true, preserveState: true },
                        );
                    }
                }}
            />
            <InputError message={error} />
            <p className="text-sm text-muted-foreground">
                {pricedSum > 0 &&
                    `Prices in the cart add up to ${formatMoney(pricedSum, currency)}. `}
                Optional, and you can add it later. It goes on the purchase when
                you complete the trip.
            </p>
        </section>
    );
}

export default function TripShow({ trip }: { trip: Trip }) {
    const formatDate = useFormatDate();
    const items = trip.items ?? [];
    const active = trip.status === 'active';
    const editable = trip.can.run;
    const done = items.filter((item) => item.purchased);
    const remaining = items.filter((item) => !item.purchased);
    const options = { preserveScroll: true, preserveState: true };

    const live = useLiveReload(`list.${trip.list.uuid}`, TRIP_EVENTS, ['trip']);
    const { start, stop } = usePoll(
        15000,
        { only: ['trip'] },
        { autoStart: false },
    );

    // Polling is the fallback for when live updates are unavailable.
    useEffect(() => {
        if (active && !live) {
            start();
        } else {
            stop();
        }
    }, [active, live, start, stop]);

    const groups = new Map<string, TripItem[]>();

    [...remaining]
        .sort((a, b) => a.category_sort - b.category_sort)
        .forEach((item) => {
            const name = item.category ?? 'No category';
            groups.set(name, [...(groups.get(name) ?? []), item]);
        });

    return (
        <>
            <Head title={`Trip: ${trip.list.title}`} />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-5 p-4 pb-28">
                <header className="flex flex-col gap-3">
                    <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0">
                            <h1 className="text-xl font-semibold break-words">
                                {trip.list.deleted ? (
                                    trip.list.title
                                ) : (
                                    <Link
                                        href={listRoutes.show(trip.list.uuid)}
                                        className="underline-offset-4 hover:underline"
                                    >
                                        {trip.list.title}
                                    </Link>
                                )}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {trip.store?.name ?? 'No store'} · Started{' '}
                                {formatDate(trip.started_at)}
                                {trip.started_by && ` by ${trip.started_by}`}
                            </p>
                        </div>
                        {!active && (
                            <Badge
                                variant={
                                    trip.status === 'completed'
                                        ? 'default'
                                        : 'secondary'
                                }
                            >
                                {trip.status === 'completed'
                                    ? 'Completed'
                                    : 'Cancelled'}
                            </Badge>
                        )}
                    </div>
                    <TripProgress done={done.length} total={items.length} />
                    {trip.status === 'completed' && (
                        <p className="text-sm text-muted-foreground">
                            Completed {formatDate(trip.completed_at)}
                            {trip.completed_by && ` by ${trip.completed_by}`}
                            {trip.purchase && (
                                <>
                                    {' · '}
                                    <Link
                                        href={purchases.show(trip.purchase)}
                                        className="text-primary underline underline-offset-4"
                                    >
                                        View purchase
                                    </Link>
                                </>
                            )}
                        </p>
                    )}
                </header>

                {[...groups.entries()].map(([name, groupItems]) => (
                    <section key={name}>
                        <h2 className="text-sm font-medium text-muted-foreground">
                            {name}
                        </h2>
                        <ul className="divide-y">
                            {groupItems.map((item) => (
                                <TripRow
                                    key={item.uuid}
                                    trip={trip}
                                    item={item}
                                    editable={editable}
                                />
                            ))}
                        </ul>
                    </section>
                ))}

                {done.length > 0 && (
                    <section>
                        <h2 className="text-sm font-medium text-muted-foreground">
                            In the cart ({done.length})
                        </h2>
                        <ul className="divide-y">
                            {done.map((item) => (
                                <TripRow
                                    key={item.uuid}
                                    trip={trip}
                                    item={item}
                                    editable={editable}
                                />
                            ))}
                        </ul>
                    </section>
                )}

                {active && (trip.added_since ?? []).length > 0 && (
                    <section className="rounded-lg border p-3">
                        <h2 className="font-medium">Added since trip began</h2>
                        <p className="mb-2 text-sm text-muted-foreground">
                            These stay on the list unless you add them to this
                            trip.
                        </p>
                        <ul className="divide-y">
                            {trip.added_since?.map((item) => (
                                <li
                                    key={item.uuid}
                                    className="flex items-center justify-between gap-2 py-2"
                                >
                                    <span className="min-w-0 break-words">
                                        {item.name}
                                        <span className="ml-2 text-sm text-muted-foreground">
                                            {formatAmount(
                                                item.quantity,
                                                item.unit,
                                            )}
                                        </span>
                                    </span>
                                    {editable && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            aria-label={`Add ${item.name} to this trip`}
                                            onClick={() =>
                                                router.post(
                                                    trips.items.include.url(
                                                        trip.uuid,
                                                    ),
                                                    { item: item.uuid },
                                                    options,
                                                )
                                            }
                                        >
                                            <Plus />
                                            Add
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
                {active && editable && trip.can.manage_amounts && (
                    <TripTotal trip={trip} items={items} />
                )}
            </div>

            {active && editable && (
                <div className="sticky bottom-0 mt-auto border-t bg-background p-3">
                    <div className="mx-auto flex max-w-2xl gap-2">
                        <ConfirmDialog
                            trigger={
                                <Button variant="outline" size="lg">
                                    Cancel trip
                                </Button>
                            }
                            title="Cancel this trip?"
                            description="Checks from this trip are discarded. Your list stays as it is."
                            confirmLabel="Cancel trip"
                            destructive
                            onConfirm={() =>
                                router.post(trips.cancel.url(trip.uuid))
                            }
                        />
                        <ConfirmDialog
                            trigger={
                                <Button size="lg" className="flex-1">
                                    Complete trip
                                </Button>
                            }
                            title="Complete this trip?"
                            description={`${done.length} purchased ${done.length === 1 ? 'item goes' : 'items go'} into history. ${remaining.length} unpurchased ${remaining.length === 1 ? 'item stays' : 'items stay'} on the list.`}
                            confirmLabel="Complete trip"
                            onConfirm={() =>
                                router.post(
                                    trips.complete.url(trip.uuid),
                                    {},
                                    options,
                                )
                            }
                        />
                    </div>
                </div>
            )}
        </>
    );
}

TripShow.layout = {
    breadcrumbs: [{ title: 'Lists', href: listRoutes.index() }],
};
