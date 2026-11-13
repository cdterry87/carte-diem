import { useState } from 'react';
import { formatDate, formatMoney, perUnit } from '@/lib/format';
import type { PricePoint } from '@/types';

/**
 * Prices over real time, oldest on the left. The axis fits the prices rather than starting
 * at zero, so small changes stay visible, and it labels its range. The table below holds the
 * same values.
 */
export function PriceChart({
    prices,
    currency,
    unit,
    timezone,
}: {
    prices: PricePoint[];
    currency: string;
    unit: string;
    timezone: string;
}) {
    const [active, setActive] = useState<number | null>(null);
    const values = prices.map((price) => price.unit_price_minor);
    const low = Math.min(...values);
    const high = Math.max(...values);
    const padding = Math.max((high - low) * 0.1, high * 0.05, 1);
    const bottom = Math.max(low - padding, 0);
    const top = high + padding;
    const times = prices.map((price) => Date.parse(price.purchased_at));
    const first = times[0];
    const span = times[times.length - 1] - first;
    const points = prices.map((price, index) => ({
        ...price,
        x: span === 0 ? 50 : ((times[index] - first) / span) * 100,
        y: 100 - ((price.unit_price_minor - bottom) / (top - bottom)) * 100,
    }));
    const activePoint = active === null ? null : points[active];

    return (
        <figure className="rounded-lg border p-3">
            <figcaption className="mb-3 flex flex-wrap justify-between gap-x-4 text-sm">
                <span className="font-medium">Price {perUnit(unit)}</span>
                <span
                    aria-hidden="true"
                    className="min-h-5 text-muted-foreground"
                >
                    {activePoint && (
                        <>
                            <span className="font-medium text-foreground">
                                {formatMoney(
                                    activePoint.unit_price_minor,
                                    currency,
                                )}
                            </span>{' '}
                            {formatDate(activePoint.purchased_at, timezone)}
                        </>
                    )}
                </span>
            </figcaption>
            <div className="grid grid-cols-[auto_1fr] gap-x-3">
                <div
                    aria-hidden="true"
                    className="flex h-40 flex-col justify-between text-right text-xs text-muted-foreground tabular-nums"
                >
                    <span>{formatMoney(Math.round(top), currency)}</span>
                    <span>{formatMoney(Math.round(bottom), currency)}</span>
                </div>
                <div className="relative mx-2 h-40 border-y">
                    <svg
                        aria-hidden="true"
                        className="absolute inset-0 size-full overflow-visible"
                        viewBox="0 0 100 100"
                        preserveAspectRatio="none"
                    >
                        <polyline
                            fill="none"
                            stroke="var(--spending-bar)"
                            strokeWidth="2"
                            vectorEffect="non-scaling-stroke"
                            points={points
                                .map((point) => `${point.x},${point.y}`)
                                .join(' ')}
                        />
                    </svg>
                    {points.map((point, index) => (
                        <div
                            key={`${point.purchased_at}-${index}`}
                            role="img"
                            tabIndex={0}
                            aria-label={`${formatDate(point.purchased_at, timezone)}: ${formatMoney(point.unit_price_minor, currency)}${point.store ? ` at ${point.store}` : ''}`}
                            className="absolute flex size-6 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full outline-offset-0 focus-visible:outline-2 focus-visible:outline-ring"
                            style={{
                                left: `${point.x}%`,
                                top: `${point.y}%`,
                            }}
                            onPointerEnter={() => setActive(index)}
                            onPointerLeave={() => setActive(null)}
                            onFocus={() => setActive(index)}
                            onBlur={() => setActive(null)}
                        >
                            <span className="size-2.5 rounded-full bg-(--spending-bar) ring-2 ring-background" />
                        </div>
                    ))}
                </div>
            </div>
        </figure>
    );
}
