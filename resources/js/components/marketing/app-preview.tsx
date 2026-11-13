import { Check, ShoppingCart, Sparkles, Users } from 'lucide-react';

const needed = [
    { name: 'Apples', amount: '2 lb' },
    { name: 'Whole milk', amount: '1 gal' },
    { name: 'Sourdough bread', amount: '' },
];

/** A static illustration of the list screen, using made-up sample items. */
export function AppPreview() {
    return (
        <div
            role="img"
            aria-label="A sample shared shopping list, with items grouped by category and a note that a household member just added oat milk."
            className="relative"
        >
            <div className="rounded-3xl border border-m-line bg-m-surface p-5 shadow-xl shadow-black/5">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <p className="font-display text-xl font-semibold">
                            Weekly groceries
                        </p>
                        <p className="text-sm text-m-muted">Our home</p>
                    </div>
                    <span className="flex items-center gap-1.5 rounded-full bg-m-green px-3 py-1.5 text-xs font-medium text-m-on-green">
                        <ShoppingCart className="size-3.5" />
                        Start trip
                    </span>
                </div>

                <p className="mt-4 text-xs font-medium tracking-wide text-m-muted uppercase">
                    Produce &amp; dairy
                </p>
                <ul className="mt-1 divide-y divide-m-line">
                    {needed.map((item) => (
                        <li
                            key={item.name}
                            className="flex items-center gap-3 py-2.5"
                        >
                            <span className="size-5 rounded-md border-2 border-m-line" />
                            <span className="flex-1 text-sm">{item.name}</span>
                            <span className="text-xs text-m-muted">
                                {item.amount}
                            </span>
                        </li>
                    ))}
                    <li className="flex items-center gap-3 py-2.5 text-m-muted">
                        <span className="flex size-5 items-center justify-center rounded-md bg-m-green text-m-on-green">
                            <Check className="size-3.5" />
                        </span>
                        <span className="flex-1 text-sm line-through">
                            Coffee
                        </span>
                    </li>
                </ul>
            </div>

            <div className="absolute -top-4 -left-3 flex max-w-56 items-center gap-2 rounded-2xl border border-m-line bg-m-surface px-3 py-2 text-xs shadow-lg shadow-black/5 sm:-left-8">
                <Users className="size-4 shrink-0 text-m-green" />
                <span>
                    <strong className="font-semibold">Alex</strong> added oat
                    milk just now
                </span>
            </div>

            <div className="absolute -right-3 -bottom-6 flex max-w-60 items-start gap-2 rounded-2xl border border-m-line bg-m-surface px-3 py-2 text-xs shadow-lg shadow-black/5 sm:-right-8">
                <Sparkles className="mt-0.5 size-4 shrink-0 text-m-accent" />
                <span>
                    <strong className="font-semibold">
                        You may need eggs.
                    </strong>{' '}
                    You usually buy them every 9 days.
                </span>
            </div>
        </div>
    );
}
