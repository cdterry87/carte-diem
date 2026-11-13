import { Head, Link, router } from '@inertiajs/react';
import { ShoppingCart, Users, X } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { ListCard } from '@/components/lists/list-card';
import { SuggestionPanel } from '@/components/suggestions/suggestion-panel';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { IconButton } from '@/components/icon-button';
import { Button } from '@/components/ui/button';
import { useFormatDate } from '@/hooks/use-format-date';
import { dashboard } from '@/routes';
import history from '@/routes/history';
import households from '@/routes/households';
import invitations from '@/routes/invitations';
import lists from '@/routes/lists';
import notices from '@/routes/notices';
import purchases from '@/routes/purchases';
import trips from '@/routes/trips';
import type {
    HouseholdInvitation,
    ListShareInvitation,
    Purchase,
    Recommendation,
    ShoppingList,
    Trip,
} from '@/types';

type Notice = 'household';

function DashboardNotice({ notice }: { notice: Notice }) {
    return (
        <Alert role="status" className="pr-12">
            <Users />
            <AlertDescription className="text-foreground">
                <p>
                    Head of a household? Create a household and invite others in
                    your home to share lists.{' '}
                    <Link
                        href={households.index()}
                        className="font-medium text-primary underline underline-offset-4"
                    >
                        Go to Households
                    </Link>
                </p>
            </AlertDescription>
            <IconButton
                label="Dismiss this tip"
                variant="ghost"
                className="absolute top-1.5 right-1.5 size-8"

                onClick={() =>
                    router.post(
                        notices.dismiss.url(notice),
                        {},
                        { preserveScroll: true },
                    )
                }
            >
                <X />
            </IconButton>
        </Alert>
    );
}

function InvitationRow({
    label,
    onAccept,
    onDecline,
}: {
    label: string;
    onAccept: () => void;
    onDecline: () => void;
}) {
    return (
        <li className="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3">
            <span className="min-w-0 break-words">{label}</span>
            <span className="flex gap-2">
                <Button size="sm" onClick={onAccept}>
                    Accept
                </Button>
                <Button size="sm" variant="outline" onClick={onDecline}>
                    Decline
                </Button>
            </span>
        </li>
    );
}

export default function Dashboard({
    notice,
    lists: recentLists,
    activeTrips,
    recentPurchases,
    householdInvitations,
    listInvitations,
    suggestions,
}: {
    notice: Notice | null;
    lists: ShoppingList[];
    activeTrips: Trip[];
    recentPurchases: Purchase[];
    householdInvitations: HouseholdInvitation[];
    listInvitations: ListShareInvitation[];
    suggestions: Recommendation[];
}) {
    const formatDate = useFormatDate();
    return (
        <>
            <Head title="Dashboard" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-8 p-4">
                {notice && <DashboardNotice notice={notice} />}

                {householdInvitations.length + listInvitations.length > 0 && (
                    <section aria-labelledby="invitations-heading">
                        <h2
                            id="invitations-heading"
                            className="mb-2 font-medium"
                        >
                            Invitations
                        </h2>
                        <ul className="grid gap-2">
                            {householdInvitations.map((invitation) => (
                                <InvitationRow
                                    key={invitation.uuid}
                                    label={`Join household ${invitation.household_name}`}
                                    onAccept={() =>
                                        router.post(
                                            invitations.household.accept.url(
                                                invitation.uuid,
                                            ),
                                        )
                                    }
                                    onDecline={() =>
                                        router.post(
                                            invitations.household.decline.url(
                                                invitation.uuid,
                                            ),
                                        )
                                    }
                                />
                            ))}
                            {listInvitations.map((invitation) => (
                                <InvitationRow
                                    key={invitation.uuid}
                                    label={`Open shared list ${invitation.list_title}`}
                                    onAccept={() =>
                                        router.post(
                                            invitations.list.accept.url(
                                                invitation.uuid,
                                            ),
                                        )
                                    }
                                    onDecline={() =>
                                        router.post(
                                            invitations.list.decline.url(
                                                invitation.uuid,
                                            ),
                                        )
                                    }
                                />
                            ))}
                        </ul>
                    </section>
                )}

                {activeTrips.length > 0 && (
                    <section aria-labelledby="trips-heading">
                        <h2 id="trips-heading" className="mb-2 font-medium">
                            Trips in progress
                        </h2>
                        <ul className="grid gap-2">
                            {activeTrips.map((trip) => (
                                <li key={trip.uuid}>
                                    <Link
                                        href={trips.show(trip.uuid)}
                                        className="flex items-center gap-3 rounded-lg border p-3 hover:bg-accent"
                                    >
                                        <ShoppingCart className="size-5 text-primary" />
                                        <span className="min-w-0 flex-1 break-words">
                                            {trip.list.title}
                                        </span>
                                        <span className="text-sm text-muted-foreground">
                                            {trip.purchased_count ?? 0} of{' '}
                                            {trip.item_count ?? 0}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <section aria-labelledby="lists-heading">
                    <div className="mb-2 flex items-center justify-between">
                        <h2 id="lists-heading" className="font-medium">
                            Your lists
                        </h2>
                        <Link
                            href={lists.index()}
                            className="text-sm text-primary underline-offset-4 hover:underline"
                        >
                            All lists
                        </Link>
                    </div>
                    {recentLists.length === 0 ? (
                        <EmptyState title="No lists yet">
                            <Link
                                href={lists.index()}
                                className="text-primary underline underline-offset-4"
                            >
                                Create your first list
                            </Link>
                        </EmptyState>
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {recentLists.map((list) => (
                                <ListCard key={list.uuid} list={list} />
                            ))}
                        </div>
                    )}
                </section>

                {suggestions.length > 0 && (
                    <SuggestionPanel
                        suggestions={suggestions}
                        targets={recentLists}
                        showOwner
                    />
                )}

                {recentPurchases.length > 0 && (
                    <section aria-labelledby="purchases-heading">
                        <div className="mb-2 flex items-center justify-between">
                            <h2 id="purchases-heading" className="font-medium">
                                Recent purchases
                            </h2>
                            <Link
                                href={history.index()}
                                className="text-sm text-primary underline-offset-4 hover:underline"
                            >
                                History
                            </Link>
                        </div>
                        <ul className="divide-y rounded-lg border">
                            {recentPurchases.map((purchase) => (
                                <li key={purchase.uuid}>
                                    <Link
                                        href={purchases.show(purchase.uuid)}
                                        className="flex justify-between gap-2 p-3 text-sm hover:bg-accent"
                                    >
                                        <span>
                                            {purchase.merchant ??
                                                purchase.owner?.name}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {purchase.item_count} items ·{' '}
                                            {formatDate(purchase.purchased_at)}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
