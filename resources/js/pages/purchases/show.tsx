import { Head, Link, router } from '@inertiajs/react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { MovePurchaseDialog } from '@/components/purchases/move-purchase-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useFormatDate } from '@/hooks/use-format-date';
import { formatAmount, formatMoney } from '@/lib/format';
import purchaseRoutes from '@/routes/purchases';
import trips from '@/routes/trips';
import type { Purchase } from '@/types';

export default function PurchaseShow({ purchase }: { purchase: Purchase }) {
    const formatDate = useFormatDate();
    const currency = purchase.currency_code;
    const needsReview = purchase.verification_status === 'needs_review';
    const amounts = [
        { label: 'Subtotal', value: purchase.subtotal_minor },
        { label: 'Tax', value: purchase.tax_minor },
        { label: 'Discount', value: purchase.discount_minor },
        { label: 'Total', value: purchase.total_minor },
    ].filter((amount) => amount.value !== null);

    return (
        <>
            <Head title="Purchase" />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
                <header className="flex flex-col gap-3">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="min-w-0 text-xl font-semibold break-words">
                            {purchase.merchant ?? 'Purchase'}
                        </h1>
                        {needsReview && (
                            <Badge variant="secondary">Needs checking</Badge>
                        )}
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {formatDate(purchase.purchased_at)} ·{' '}
                        {purchase.owner?.name}
                        {purchase.trip && (
                            <>
                                {' · '}
                                <Link
                                    href={trips.show(purchase.trip)}
                                    className="text-primary underline underline-offset-4"
                                >
                                    View trip
                                </Link>
                            </>
                        )}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline" size="sm">
                            <Link href={purchaseRoutes.edit(purchase.uuid)}>
                                {purchase.total_minor === null
                                    ? 'Add amount'
                                    : 'Edit'}
                            </Link>
                        </Button>
                        {needsReview && (
                            <Button
                                size="sm"
                                onClick={() =>
                                    router.patch(
                                        purchaseRoutes.update.url(
                                            purchase.uuid,
                                        ),
                                        {
                                            needs_review: false,
                                            revision: purchase.revision,
                                        },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Mark as checked
                            </Button>
                        )}
                        {purchase.can?.move && (
                            <MovePurchaseDialog purchase={purchase} />
                        )}
                        {purchase.can?.delete && (
                            <ConfirmDialog
                                trigger={
                                    <Button variant="outline" size="sm">
                                        Delete
                                    </Button>
                                }
                                title="Delete this purchase?"
                                description="It leaves spending and suggestions. You can restore it from Purchases."
                                confirmLabel="Delete purchase"
                                destructive
                                onConfirm={() =>
                                    router.delete(
                                        purchaseRoutes.destroy.url(
                                            purchase.uuid,
                                        ),
                                    )
                                }
                            />
                        )}
                    </div>
                </header>

                {amounts.length > 0 ? (
                    <dl className="grid grid-cols-2 gap-3 rounded-lg border p-3 sm:grid-cols-4">
                        {amounts.map((amount) => (
                            <div key={amount.label}>
                                <dt className="text-sm text-muted-foreground">
                                    {amount.label}
                                </dt>
                                <dd className="font-medium">
                                    {formatMoney(
                                        amount.value as number,
                                        currency,
                                    )}
                                </dd>
                            </div>
                        ))}
                    </dl>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        No amount yet, so this purchase is not in spending.
                    </p>
                )}

                {(purchase.items?.length ?? 0) > 0 && (
                    <ul className="divide-y rounded-lg border">
                        {purchase.items?.map((item) => (
                            <li
                                key={item.uuid}
                                className="flex justify-between gap-4 p-3"
                            >
                                <span className="min-w-0 break-words">
                                    {item.description}
                                    {item.category && (
                                        <span className="block text-sm text-muted-foreground">
                                            {item.category}
                                        </span>
                                    )}
                                </span>
                                <span className="shrink-0 text-right text-sm text-muted-foreground">
                                    {formatAmount(item.quantity, item.unit)}
                                    {item.line_total_minor !== null && (
                                        <span className="block font-medium text-foreground">
                                            {formatMoney(
                                                item.line_total_minor,
                                                currency,
                                            )}
                                        </span>
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

PurchaseShow.layout = {
    breadcrumbs: [{ title: 'Purchases', href: purchaseRoutes.index() }],
};
