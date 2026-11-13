import { Head } from '@inertiajs/react';
import { PurchaseForm } from '@/components/purchases/purchase-form';
import purchases from '@/routes/purchases';
import type { Owner, Purchase } from '@/types';

export default function PurchaseEdit({
    purchase,
    stores,
    categories,
}: {
    purchase: Purchase;
    stores: string[];
    categories: { uuid: string; name: string }[];
}) {
    return (
        <>
            <Head title="Edit purchase" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Edit purchase</h1>
                <PurchaseForm
                    owner={purchase.owner as Owner}
                    purchase={purchase}
                    currency={purchase.currency_code}
                    stores={stores}
                    categories={categories}
                />
            </div>
        </>
    );
}

PurchaseEdit.layout = {
    breadcrumbs: [{ title: 'Purchases', href: purchases.index() }],
};
