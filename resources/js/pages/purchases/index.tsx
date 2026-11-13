import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { WorkspaceNav } from '@/components/workspace-nav';
import { useFormatDate } from '@/hooks/use-format-date';
import { formatMoney } from '@/lib/format';
import purchaseRoutes from '@/routes/purchases';
import spending from '@/routes/spending';
import type { Owner, Purchase } from '@/types';

export default function PurchasesIndex({
    owner,
    purchases,
    pagination,
    deleted,
}: {
    owner: Owner;
    purchases: Purchase[];
    pagination: { next: string | null; previous: string | null };
    deleted: Purchase[];
}) {
    const formatDate = useFormatDate();
    const scopeQuery: Record<string, string> =
        owner.type === 'household' ? { household: owner.uuid } : {};

    return (
        <>
            <Head title="Purchases" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
                <header className="flex flex-col gap-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h1 className="text-xl font-semibold">Purchases</h1>
                        <div className="flex gap-2">
                            <Button asChild variant="outline">
                                <Link
                                    href={spending.index({ query: scopeQuery })}
                                >
                                    View spending
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link
                                    href={purchaseRoutes.create({
                                        query: scopeQuery,
                                    })}
                                >
                                    <Plus />
                                    Add purchase
                                </Link>
                            </Button>
                        </div>
                    </div>
                    <WorkspaceNav
                        current={owner.uuid}
                        hrefFor={(query) => purchaseRoutes.index({ query })}
                    />
                </header>

                {purchases.length === 0 && pagination.previous === null ? (
                    <EmptyState title="No purchases yet">
                        Add one by hand, or complete a shopping trip.
                    </EmptyState>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {purchases.map((purchase) => (
                            <li key={purchase.uuid}>
                                <Link
                                    href={purchaseRoutes.show(purchase.uuid)}
                                    className="flex flex-wrap items-baseline justify-between gap-x-4 p-3 hover:bg-accent"
                                >
                                    <span className="min-w-0 break-words">
                                        {purchase.merchant ?? 'Purchase'}
                                        <span className="block text-sm text-muted-foreground">
                                            {formatDate(purchase.purchased_at)}
                                        </span>
                                    </span>
                                    <span className="flex items-center gap-2">
                                        {purchase.verification_status ===
                                            'needs_review' && (
                                            <Badge variant="secondary">
                                                Needs checking
                                            </Badge>
                                        )}
                                        {purchase.total_minor !== null ? (
                                            <span className="font-medium">
                                                {formatMoney(
                                                    purchase.total_minor,
                                                    purchase.currency_code,
                                                )}
                                            </span>
                                        ) : (
                                            <span className="text-sm text-muted-foreground">
                                                No amount
                                            </span>
                                        )}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}

                {(pagination.next || pagination.previous) && (
                    <nav
                        aria-label="Purchase pages"
                        className="flex justify-between"
                    >
                        {pagination.previous ? (
                            <Button asChild variant="outline" size="sm">
                                <Link href={pagination.previous}>Newer</Link>
                            </Button>
                        ) : (
                            <span />
                        )}
                        {pagination.next && (
                            <Button asChild variant="outline" size="sm">
                                <Link href={pagination.next}>Older</Link>
                            </Button>
                        )}
                    </nav>
                )}

                {deleted.length > 0 && (
                    <section aria-labelledby="deleted-purchases-heading">
                        <h2
                            id="deleted-purchases-heading"
                            className="mb-2 font-medium"
                        >
                            Recently deleted
                        </h2>
                        <ul className="divide-y rounded-lg border">
                            {deleted.map((purchase) => (
                                <li
                                    key={purchase.uuid}
                                    className="flex items-center justify-between gap-2 p-3"
                                >
                                    <span className="min-w-0 break-words">
                                        {purchase.merchant ?? 'Purchase'}
                                        <span className="block text-sm text-muted-foreground">
                                            {formatDate(purchase.purchased_at)}
                                        </span>
                                    </span>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        aria-label={`Restore ${purchase.merchant ?? 'purchase'}`}
                                        onClick={() =>
                                            router.post(
                                                purchaseRoutes.restore.url(
                                                    purchase.uuid,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Restore
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

PurchasesIndex.layout = {
    breadcrumbs: [{ title: 'Purchases', href: purchaseRoutes.index() }],
};
