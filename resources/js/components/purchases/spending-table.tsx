import { formatMoney } from '@/lib/format';
import type { SpendingRow } from '@/types';

export function SpendingTable({
    caption,
    heading,
    rows,
    currency,
    showBars = false,
}: {
    caption: string;
    heading: string;
    rows: (SpendingRow & { label: string })[];
    currency: string;
    showBars?: boolean;
}) {
    const largest = Math.max(...rows.map((row) => row.total_minor), 1);

    return (
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full text-sm">
                <caption className="p-3 text-left font-medium">
                    {caption}
                </caption>
                <thead>
                    <tr className="border-t text-muted-foreground">
                        <th scope="col" className="p-3 text-left font-normal">
                            {heading}
                        </th>
                        <th scope="col" className="p-3 text-right font-normal">
                            Purchases
                        </th>
                        <th scope="col" className="p-3 text-right font-normal">
                            Spent
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.label} className="border-t">
                            <th
                                scope="row"
                                className="p-3 text-left font-normal break-words"
                            >
                                {row.label}
                                {showBars && (
                                    <div
                                        aria-hidden="true"
                                        className="mt-1 h-1.5 rounded-full bg-(--spending-bar)"
                                        style={{
                                            width: `${(row.total_minor / largest) * 100}%`,
                                        }}
                                    />
                                )}
                            </th>
                            <td className="p-3 text-right">
                                {row.purchase_count}
                            </td>
                            <td className="p-3 text-right font-medium">
                                {formatMoney(row.total_minor, currency)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
