import { useState } from 'react';
import { SpendingChart } from '@/components/purchases/spending-chart';
import { SpendingTable } from '@/components/purchases/spending-table';
import { formatMoney } from '@/lib/format';
import type { SpendingCategory } from '@/types';

const NO_CATEGORY = 'No category';

export function CategorySpending({
    categories,
    currency,
    itemizedMinor,
    totalMinor,
}: {
    categories: SpendingCategory[];
    currency: string;
    itemizedMinor: number;
    totalMinor: number;
}) {
    const labelled = categories.map((category) => ({
        ...category,
        label: category.name ?? NO_CATEGORY,
    }));
    const [chosen, setChosen] = useState<string | null>(null);
    const selected =
        labelled.find((category) => category.label === chosen) ?? labelled[0];

    if (selected === undefined) {
        return (
            <p className="text-sm text-muted-foreground">
                Add prices to the items on a purchase to see spending by
                category.
            </p>
        );
    }

    return (
        <div className="grid gap-4">
            <SpendingTable
                caption="By category"
                heading="Category"
                currency={currency}
                rows={labelled}
                showBars
            />
            <p className="text-sm text-muted-foreground">
                Categories use {formatMoney(itemizedMinor, currency)} in item
                prices, out of {formatMoney(totalMinor, currency)} spent. Tax,
                discounts, and items with no price make up the difference.
            </p>
            <label className="grid gap-2 text-sm font-medium">
                Category to chart
                <select
                    className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm font-normal shadow-xs"
                    value={selected.label}
                    onChange={(event) => setChosen(event.target.value)}
                >
                    {labelled.map((category) => (
                        <option key={category.label} value={category.label}>
                            {category.label}
                        </option>
                    ))}
                </select>
            </label>
            <SpendingChart
                key={selected.label}
                months={selected.by_month}
                currency={currency}
                title={`${selected.label} each month`}
            />
        </div>
    );
}
