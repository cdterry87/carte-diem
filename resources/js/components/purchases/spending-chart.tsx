import { useState } from 'react';
import { formatMoney, monthLabel } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { SpendingRow } from '@/types';

/** The next round number up, for a clean top tick: 8700 becomes 10000. */
function niceCeiling(value: number): number {
    if (value <= 0) {
        return 100;
    }

    const magnitude = 10 ** Math.floor(Math.log10(value));

    for (const step of [1, 2, 5, 10]) {
        if (step * magnitude >= value) {
            return step * magnitude;
        }
    }

    return 10 * magnitude;
}

/**
 * One series, so the heading names it and no legend is needed. The table below
 * it holds the same values, so hover and focus only ever add convenience.
 */
export function SpendingChart({
    months,
    currency,
    title = 'Spent each month',
}: {
    months: (SpendingRow & { month: string })[];
    currency: string;
    title?: string;
}) {
    const [active, setActive] = useState<string | null>(null);
    const oldestFirst = [...months].reverse();
    const ceiling = niceCeiling(
        Math.max(...oldestFirst.map((row) => row.total_minor)),
    );
    const labelEvery = Math.ceil(oldestFirst.length / 6);
    const whole = (minor: number) =>
        formatMoney(minor, currency).replace(/\.00$/, '');
    const activeRow = oldestFirst.find((row) => row.month === active);

    return (
        <figure className="rounded-lg border p-3">
            <figcaption className="mb-3 flex flex-wrap justify-between gap-x-4 text-sm">
                <span className="font-medium">{title}</span>
                <span
                    aria-hidden="true"
                    className="min-h-5 text-muted-foreground"
                >
                    {activeRow && (
                        <>
                            <span className="font-medium text-foreground">
                                {formatMoney(activeRow.total_minor, currency)}
                            </span>{' '}
                            {monthLabel(activeRow.month)}
                        </>
                    )}
                </span>
            </figcaption>
            <div className="grid grid-cols-[auto_1fr] gap-x-2">
                <div
                    aria-hidden="true"
                    className="flex h-40 flex-col justify-between text-right text-xs text-muted-foreground tabular-nums"
                >
                    <span className="-translate-y-1/2">{whole(ceiling)}</span>
                    <span>{whole(ceiling / 2)}</span>
                    <span className="translate-y-1/2">{whole(0)}</span>
                </div>
                <div className="relative h-40 border-b">
                    <div
                        aria-hidden="true"
                        className="absolute inset-x-0 top-0 border-t"
                    />
                    <div
                        aria-hidden="true"
                        className="absolute inset-x-0 top-1/2 border-t"
                    />
                    <div className="absolute inset-0 flex gap-0.5">
                        {oldestFirst.map((row) => (
                            <div
                                key={row.month}
                                role="img"
                                tabIndex={0}
                                aria-label={`${monthLabel(row.month)}: ${formatMoney(row.total_minor, currency)}, ${row.purchase_count} ${row.purchase_count === 1 ? 'purchase' : 'purchases'}`}
                                className="flex min-w-0 flex-1 items-end justify-center rounded-sm outline-offset-2 focus-visible:outline-2 focus-visible:outline-ring"
                                onPointerEnter={() => setActive(row.month)}
                                onPointerLeave={() => setActive(null)}
                                onFocus={() => setActive(row.month)}
                                onBlur={() => setActive(null)}
                            >
                                {row.total_minor > 0 && (
                                    <div
                                        className={cn(
                                            'w-full max-w-6 rounded-t bg-(--spending-bar) transition-opacity',
                                            active !== null &&
                                                active !== row.month &&
                                                'opacity-60',
                                        )}
                                        style={{
                                            height: `${(row.total_minor / ceiling) * 100}%`,
                                        }}
                                    />
                                )}
                            </div>
                        ))}
                    </div>
                </div>
                <div />
                <div
                    aria-hidden="true"
                    className="mt-1 flex gap-0.5 text-xs text-muted-foreground"
                >
                    {oldestFirst.map((row, index) => (
                        <span
                            key={row.month}
                            className="min-w-0 flex-1 overflow-visible text-center whitespace-nowrap"
                        >
                            {(oldestFirst.length - 1 - index) % labelEvery === 0
                                ? monthLabel(row.month, { month: 'short' })
                                : ''}
                        </span>
                    ))}
                </div>
            </div>
        </figure>
    );
}
