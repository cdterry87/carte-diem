import { Head } from '@inertiajs/react';
import { PurchaseForm } from '@/components/purchases/purchase-form';
import purchases from '@/routes/purchases';
import type { Owner } from '@/types';

export default function PurchaseCreate({
    owner,
    currency,
    stores,
    categories,
}: {
    owner: Owner;
    currency: string;
    stores: string[];
    categories: { uuid: string; name: string }[];
}) {
    return (
        <>
            <Head title="Add purchase" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
                <header>
                    <h1 className="text-xl font-semibold">Add purchase</h1>
                    <p className="text-sm text-muted-foreground">
                        Saved to {owner.name}.
                    </p>
                </header>
                <PurchaseForm
                    owner={owner}
                    currency={currency}
                    stores={stores}
                    categories={categories}
                />
            </div>
        </>
    );
}

PurchaseCreate.layout = {
    breadcrumbs: [
        { title: 'Purchases', href: purchases.index() },
        { title: 'Add purchase', href: purchases.create() },
    ],
};
