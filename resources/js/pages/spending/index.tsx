import { Head, Link } from '@inertiajs/react';
import { EmptyState } from '@/components/empty-state';
import { CategorySpending } from '@/components/purchases/category-spending';
import { SpendingChart } from '@/components/purchases/spending-chart';
import { SpendingTable } from '@/components/purchases/spending-table';
import { WorkspaceNav } from '@/components/workspace-nav';
import { formatMoney, monthLabel } from '@/lib/format';
import { cn } from '@/lib/utils';
import purchases from '@/routes/purchases';
import spending from '@/routes/spending';
import type { Owner, SpendingComparison, SpendingSummary } from '@/types';

/** "down 12% from September" */
function change(current: number, earlier: number, label: string): string {
    const percent = Math.round(((current - earlier) / earlier) * 100);

    if (percent === 0) {
        return `about the same as ${label}`;
    }

    return `${percent > 0 ? 'up' : 'down'} ${Math.abs(percent)}% from ${label}`;
}

function MonthComparison({
    comparison,
    currency,
}: {
    comparison: SpendingComparison;
    currency: string;
}) {
    const { month, total_minor, previous_month_minor, last_year_minor } =
        comparison;
    const [year, number] = month.split('-').map(Number);
    const monthBefore = new Date(year, number - 2);
    const parts = [
        previous_month_minor !== null &&
            change(
                total_minor,
                previous_month_minor,
                monthBefore.toLocaleDateString(undefined, { month: 'long' }),
            ),
        last_year_minor !== null &&
            change(
                total_minor,
                last_year_minor,
                monthLabel(`${year - 1}-${String(number).padStart(2, '0')}`),
            ),
    ].filter(Boolean);

    return (
        <p className="text-sm text-muted-foreground">
            {monthLabel(month)}: {formatMoney(total_minor, currency)}
            {parts.length > 0 && `, ${parts.join(' and ')}`}.
        </p>
    );
}

export default function SpendingIndex({
    owner,
    summary,
    periods,
}: {
    owner: Owner;
    summary: SpendingSummary;
    periods: number[];
}) {
    const scopeQuery: Record<string, string> =
        owner.type === 'household' ? { household: owner.uuid } : {};
    const withMonths = (query: Record<string, string>) => ({
        ...query,
        months: String(summary.months),
    });

    return (
        <>
            <Head title="Spending" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
                <header className="flex flex-col gap-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h1 className="text-xl font-semibold">Spending</h1>
                        <Link
                            href={spending.prices({ query: scopeQuery })}
                            className="text-sm text-primary underline underline-offset-4"
                        >
                            Price history
                        </Link>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Confirmed purchases with a total. Months follow the{' '}
                        {summary.timezone} time zone.
                    </p>
                    <WorkspaceNav
                        current={owner.uuid}
                        hrefFor={(query) =>
                            spending.index({ query: withMonths(query) })
                        }
                    />
                    <nav aria-label="Period" className="flex flex-wrap gap-2">
                        {periods.map((months) => (
                            <Link
                                key={months}
                                href={spending.index({
                                    query: {
                                        ...scopeQuery,
                                        months: String(months),
                                    },
                                })}
                                aria-current={
                                    months === summary.months
                                        ? 'page'
                                        : undefined
                                }
                                className={cn(
                                    'rounded-md border px-3 py-1.5 text-sm',
                                    months === summary.months
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'hover:bg-accent',
                                )}
                            >
                                {months} months
                            </Link>
                        ))}
                    </nav>
                </header>

                {summary.currencies.length === 0 ? (
                    <EmptyState title="No spending yet">
                        Add a total to a purchase and it shows up here.
                    </EmptyState>
                ) : (
                    summary.currencies.map((currency) => (
                        <section
                            key={currency.currency_code}
                            className="grid gap-4"
                            aria-label={`Spending in ${currency.currency_code}`}
                        >
                            <p>
                                <span className="text-2xl font-semibold">
                                    {formatMoney(
                                        currency.total_minor,
                                        currency.currency_code,
                                    )}
                                </span>{' '}
                                <span className="text-sm text-muted-foreground">
                                    across {currency.purchase_count}{' '}
                                    {currency.purchase_count === 1
                                        ? 'purchase'
                                        : 'purchases'}
                                </span>
                            </p>
                            {currency.comparison && (
                                <MonthComparison
                                    comparison={currency.comparison}
                                    currency={currency.currency_code}
                                />
                            )}
                            <SpendingChart
                                months={currency.by_month}
                                currency={currency.currency_code}
                            />
                            <SpendingTable
                                caption="By month"
                                heading="Month"
                                currency={currency.currency_code}
                                rows={currency.by_month.map((row) => ({
                                    ...row,
                                    label: monthLabel(row.month),
                                }))}
                            />
                            <SpendingTable
                                caption="By store"
                                heading="Store"
                                currency={currency.currency_code}
                                rows={currency.by_store.map((row) => ({
                                    ...row,
                                    label: row.name ?? 'No store',
                                }))}
                            />
                            <CategorySpending
                                categories={currency.by_category}
                                currency={currency.currency_code}
                                itemizedMinor={currency.itemized_minor}
                                totalMinor={currency.total_minor}
                            />
                        </section>
                    ))
                )}

                {(summary.missing_total_count > 0 ||
                    summary.needs_review_count > 0) && (
                    <p className="text-sm text-muted-foreground">
                        Not counted:{' '}
                        {[
                            summary.missing_total_count > 0 &&
                                `${summary.missing_total_count} with no amount`,
                            summary.needs_review_count > 0 &&
                                `${summary.needs_review_count} still to check`,
                        ]
                            .filter(Boolean)
                            .join(' and ')}
                        .{' '}
                        <Link
                            href={purchases.index({ query: scopeQuery })}
                            className="text-primary underline underline-offset-4"
                        >
                            Review purchases
                        </Link>
                    </p>
                )}
            </div>
        </>
    );
}

SpendingIndex.layout = {
    breadcrumbs: [{ title: 'Spending', href: spending.index() }],
};
