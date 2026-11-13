import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { PriceChart } from '@/components/purchases/price-chart';
import { Input } from '@/components/ui/input';
import { WorkspaceNav } from '@/components/workspace-nav';
import { formatDate, formatMoney, perUnit } from '@/lib/format';
import spending from '@/routes/spending';
import type { Owner, PricedProduct, PriceSeries, ProductPrices } from '@/types';

function SeriesDetail({
    series,
    timezone,
}: {
    series: PriceSeries;
    timezone: string;
}) {
    const currency = series.currency_code;
    const money = (minor: number) => formatMoney(minor, currency);

    return (
        <section
            className="grid gap-4"
            aria-label={`Prices ${perUnit(series.unit)} in ${currency}`}
        >
            {series.prices.length > 1 && (
                <PriceChart
                    prices={series.prices}
                    currency={currency}
                    unit={series.unit}
                    timezone={timezone}
                />
            )}
            {series.stores.length > 1 && (
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <caption className="p-3 text-left font-medium">
                            By store, lowest average first
                        </caption>
                        <thead>
                            <tr className="border-t text-muted-foreground">
                                <th
                                    scope="col"
                                    className="p-3 text-left font-normal"
                                >
                                    Store
                                </th>
                                <th
                                    scope="col"
                                    className="p-3 text-right font-normal"
                                >
                                    Average
                                </th>
                                <th
                                    scope="col"
                                    className="p-3 text-right font-normal"
                                >
                                    Latest
                                </th>
                                <th
                                    scope="col"
                                    className="p-3 text-right font-normal"
                                >
                                    Bought
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {series.stores.map((store) => (
                                <tr key={store.name ?? ''} className="border-t">
                                    <th
                                        scope="row"
                                        className="p-3 text-left font-normal break-words"
                                    >
                                        {store.name ?? 'No store'}
                                    </th>
                                    <td className="p-3 text-right font-medium">
                                        {money(store.average_minor)}
                                    </td>
                                    <td className="p-3 text-right">
                                        {money(store.latest_minor)}
                                        <span className="block text-xs text-muted-foreground">
                                            {formatDate(
                                                store.latest_at,
                                                timezone,
                                            )}
                                        </span>
                                    </td>
                                    <td className="p-3 text-right">
                                        {store.purchase_count}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                    <caption className="p-3 text-left font-medium">
                        Every purchase, price {perUnit(series.unit)}
                    </caption>
                    <thead>
                        <tr className="border-t text-muted-foreground">
                            <th
                                scope="col"
                                className="p-3 text-left font-normal"
                            >
                                Date
                            </th>
                            <th
                                scope="col"
                                className="p-3 text-left font-normal"
                            >
                                Store
                            </th>
                            <th
                                scope="col"
                                className="p-3 text-right font-normal"
                            >
                                Price
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {[...series.prices].reverse().map((price, index) => (
                            <tr
                                key={`${price.purchased_at}-${index}`}
                                className="border-t"
                            >
                                <th
                                    scope="row"
                                    className="p-3 text-left font-normal"
                                >
                                    {formatDate(price.purchased_at, timezone)}
                                </th>
                                <td className="p-3 break-words">
                                    {price.store ?? 'No store'}
                                </td>
                                <td className="p-3 text-right font-medium">
                                    {money(price.unit_price_minor)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function priceChange(product: PricedProduct): string {
    const percent = Math.round(
        ((product.latest_minor - product.first_minor) / product.first_minor) *
            100,
    );

    if (percent === 0) {
        return 'No change';
    }

    return `${percent > 0 ? 'Up' : 'Down'} ${Math.abs(percent)}%`;
}

function ProductList({
    products,
    scopeQuery,
}: {
    products: PricedProduct[];
    scopeQuery: Record<string, string>;
}) {
    const [search, setSearch] = useState('');
    const needle = search.trim().toLowerCase();
    const shown = products.filter((product) =>
        product.name.toLowerCase().includes(needle),
    );

    return (
        <div className="grid gap-4">
            <label className="grid gap-2 text-sm font-medium">
                Find a product
                <Input
                    type="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                />
            </label>
            {shown.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No products match.
                </p>
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <caption className="sr-only">
                            Products with price history
                        </caption>
                        <thead>
                            <tr className="text-muted-foreground">
                                <th
                                    scope="col"
                                    className="p-3 text-left font-normal"
                                >
                                    Product
                                </th>
                                <th
                                    scope="col"
                                    className="p-3 text-right font-normal"
                                >
                                    Latest
                                </th>
                                <th
                                    scope="col"
                                    className="p-3 text-right font-normal"
                                >
                                    Since first
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {shown.map((product) => (
                                <tr
                                    key={`${product.uuid}-${product.currency_code}-${product.unit}`}
                                    className="border-t"
                                >
                                    <th
                                        scope="row"
                                        className="p-3 text-left font-normal break-words"
                                    >
                                        <Link
                                            href={spending.prices({
                                                query: {
                                                    ...scopeQuery,
                                                    product: product.uuid,
                                                },
                                            })}
                                            className="text-primary underline underline-offset-4"
                                        >
                                            {product.name}
                                        </Link>
                                    </th>
                                    <td className="p-3 text-right font-medium whitespace-nowrap">
                                        {formatMoney(
                                            product.latest_minor,
                                            product.currency_code,
                                        )}
                                        <span className="block text-xs font-normal text-muted-foreground">
                                            {perUnit(product.unit)}
                                        </span>
                                    </td>
                                    <td className="p-3 text-right whitespace-nowrap">
                                        {priceChange(product)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

export default function SpendingPrices({
    owner,
    products,
    product,
    timezone,
    months,
}: {
    owner: Owner;
    products: PricedProduct[];
    product: ProductPrices | null;
    timezone: string;
    months: number;
}) {
    const scopeQuery: Record<string, string> =
        owner.type === 'household' ? { household: owner.uuid } : {};

    return (
        <>
            <Head
                title={product ? `${product.name} prices` : 'Price history'}
            />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
                <header className="flex flex-col gap-3">
                    <h1 className="text-xl font-semibold break-words">
                        {product ? product.name : 'Price history'}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        What you paid on confirmed purchases in the last{' '}
                        {months} months. These are your past prices, not current
                        store prices.
                    </p>
                    {product ? (
                        <Link
                            href={spending.prices({ query: scopeQuery })}
                            className="text-sm text-primary underline underline-offset-4"
                        >
                            All products
                        </Link>
                    ) : (
                        <>
                            <Link
                                href={spending.index({ query: scopeQuery })}
                                className="text-sm text-primary underline underline-offset-4"
                            >
                                Back to spending
                            </Link>
                            <WorkspaceNav
                                current={owner.uuid}
                                hrefFor={(query) => spending.prices({ query })}
                            />
                        </>
                    )}
                </header>

                {product ? (
                    product.series.map((series) => (
                        <SeriesDetail
                            key={`${series.currency_code}-${series.unit}`}
                            series={series}
                            timezone={timezone}
                        />
                    ))
                ) : products.length === 0 ? (
                    <EmptyState title="No price history yet">
                        Add item prices to purchases. A product shows up here
                        once it has two prices in the same unit.
                    </EmptyState>
                ) : (
                    <ProductList products={products} scopeQuery={scopeQuery} />
                )}
            </div>
        </>
    );
}

SpendingPrices.layout = {
    breadcrumbs: [
        { title: 'Spending', href: spending.index() },
        { title: 'Price history', href: spending.prices() },
    ],
};
