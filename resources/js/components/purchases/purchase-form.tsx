import { useForm, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import AlertError from '@/components/alert-error';
import InputError from '@/components/input-error';
import { IconButton } from '@/components/icon-button';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    formatMoney,
    formatQuantity,
    minorToInput,
    parseMoney,
} from '@/lib/format';
import purchases from '@/routes/purchases';
import type { Owner, Purchase } from '@/types';

type Line = {
    uuid: string | null;
    description: string;
    quantity: string;
    unit: string;
    category: string;
    price: string;
};

const AMOUNTS = [
    { field: 'subtotal', input: 'subtotal_minor', label: 'Subtotal' },
    { field: 'tax', input: 'tax_minor', label: 'Tax' },
    { field: 'discount', input: 'discount_minor', label: 'Discount' },
    { field: 'total', input: 'total_minor', label: 'Total' },
] as const;

const INVALID_AMOUNT = 'Enter an amount like 12.50.';

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';

export function PurchaseForm({
    owner,
    purchase,
    currency,
    stores,
    categories,
}: {
    owner: Owner;
    purchase?: Purchase;
    currency: string;
    stores: string[];
    categories: { uuid: string; name: string }[];
}) {
    const { auth } = usePage().props;
    const today = new Date().toLocaleDateString('en-CA', {
        timeZone: auth.user.timezone,
    });

    const form = useForm({
        merchant: purchase?.merchant ?? '',
        purchased_on: purchase?.purchased_on ?? today,
        subtotal: minorToInput(purchase?.subtotal_minor, currency),
        tax: minorToInput(purchase?.tax_minor, currency),
        discount: minorToInput(purchase?.discount_minor, currency),
        total: minorToInput(purchase?.total_minor, currency),
        needs_review: purchase?.verification_status === 'needs_review',
        items: (purchase?.items ?? []).map((item): Line => ({
            uuid: item.uuid,
            description: item.description,
            quantity: formatQuantity(item.quantity),
            unit: item.unit ?? '',
            category: item.category_uuid ?? '',
            price: minorToInput(item.line_total_minor, currency),
        })),
    });
    const errors = form.errors as Record<string, string | undefined>;

    const setLine = (index: number, patch: Partial<Line>) =>
        form.setData(
            'items',
            form.data.items.map((line, position) =>
                position === index ? { ...line, ...patch } : line,
            ),
        );

    const parsedLines = form.data.items.map((line) =>
        parseMoney(line.price, currency),
    );
    const linesTotal = parsedLines.reduce<number>(
        (sum, price) => sum + (price ?? 0),
        0,
    );
    const total = parseMoney(form.data.total, currency);

    const submit = (allowDuplicate: boolean) => {
        form.clearErrors();
        const invalid: Record<string, string> = {};
        const amounts: Record<string, number | null> = {};

        for (const { field, input } of AMOUNTS) {
            const value = parseMoney(form.data[field], currency);

            if (value === undefined) {
                invalid[input] = INVALID_AMOUNT;
            } else {
                amounts[input] = value;
            }
        }

        parsedLines.forEach((price, index) => {
            if (price === undefined) {
                invalid[`items.${index}.line_total_minor`] = INVALID_AMOUNT;
            }
        });

        if (Object.keys(invalid).length > 0) {
            form.setError(invalid as never);

            return;
        }

        form.transform((data) => ({
            ...(purchase
                ? { revision: purchase.revision }
                : {
                      owner: { type: owner.type, uuid: owner.uuid },
                  }),
            merchant: data.merchant,
            purchased_on: data.purchased_on,
            needs_review: data.needs_review,
            allow_duplicate: allowDuplicate,
            ...amounts,
            items: data.items.map((line, index) => ({
                ...(line.uuid ? { uuid: line.uuid } : {}),
                description: line.description,
                quantity: line.quantity === '' ? null : line.quantity,
                unit: line.unit === '' ? null : line.unit,
                category: line.category === '' ? null : line.category,
                line_total_minor: parsedLines[index] ?? null,
            })),
        }));

        if (purchase) {
            form.patch(purchases.update.url(purchase.uuid));
        } else {
            form.post(purchases.store.url());
        }
    };

    return (
        <form
            className="grid gap-6"
            onSubmit={(event: FormEvent) => {
                event.preventDefault();
                submit(false);
            }}
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="merchant">Store or merchant</Label>
                    <Input
                        id="merchant"
                        list="purchase-stores"
                        required={!purchase}
                        value={form.data.merchant}
                        onChange={(event) =>
                            form.setData('merchant', event.target.value)
                        }
                    />
                    <datalist id="purchase-stores">
                        {stores.map((store) => (
                            <option key={store} value={store} />
                        ))}
                    </datalist>
                    <InputError message={errors.merchant} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="purchased_on">Date</Label>
                    <Input
                        id="purchased_on"
                        type="date"
                        required
                        max={today}
                        value={form.data.purchased_on}
                        onChange={(event) =>
                            form.setData('purchased_on', event.target.value)
                        }
                    />
                    <InputError message={errors.purchased_on} />
                </div>
            </div>

            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                {AMOUNTS.map(({ field, input, label }) => (
                    <div key={field} className="grid content-start gap-2">
                        <Label htmlFor={field}>
                            {label}
                            {field !== 'total' && (
                                <span className="font-normal text-muted-foreground">
                                    {' '}
                                    (optional)
                                </span>
                            )}
                        </Label>
                        <Input
                            id={field}
                            inputMode="decimal"
                            required={field === 'total' && !purchase}
                            value={form.data[field]}
                            onChange={(event) =>
                                form.setData(field, event.target.value)
                            }
                        />
                        <InputError message={errors[input]} />
                    </div>
                ))}
            </div>

            <fieldset className="grid gap-3">
                <legend className="mb-2 font-medium">
                    Items{' '}
                    <span className="text-sm font-normal text-muted-foreground">
                        (optional)
                    </span>
                </legend>
                {form.data.items.map((line, index) => {
                    const number = index + 1;

                    return (
                        <div
                            key={line.uuid ?? index}
                            className="grid gap-2 rounded-lg border p-3 lg:grid-cols-[1fr_5rem_5rem_9rem_6rem_auto]"
                        >
                            <Input
                                required
                                aria-label={`Item ${number} name`}
                                placeholder="Item"
                                value={line.description}
                                onChange={(event) =>
                                    setLine(index, {
                                        description: event.target.value,
                                    })
                                }
                            />
                            <Input
                                aria-label={`Item ${number} quantity`}
                                inputMode="decimal"
                                placeholder="Qty"
                                value={line.quantity}
                                onChange={(event) =>
                                    setLine(index, {
                                        quantity: event.target.value,
                                    })
                                }
                            />
                            <Input
                                aria-label={`Item ${number} unit`}
                                placeholder="Unit"
                                value={line.unit}
                                onChange={(event) =>
                                    setLine(index, { unit: event.target.value })
                                }
                            />
                            <select
                                aria-label={`Item ${number} category`}
                                className={selectClass}
                                value={line.category}
                                onChange={(event) =>
                                    setLine(index, {
                                        category: event.target.value,
                                    })
                                }
                            >
                                <option value="">Automatic</option>
                                {categories.map((category) => (
                                    <option
                                        key={category.uuid}
                                        value={category.uuid}
                                    >
                                        {category.name}
                                    </option>
                                ))}
                            </select>
                            <Input
                                aria-label={`Item ${number} price`}
                                inputMode="decimal"
                                placeholder="Price"
                                value={line.price}
                                onChange={(event) =>
                                    setLine(index, {
                                        price: event.target.value,
                                    })
                                }
                            />
                            <IconButton
                                label={`Remove item ${number}`}
                                type="button"
                                variant="ghost"

                                onClick={() =>
                                    form.setData(
                                        'items',
                                        form.data.items.filter(
                                            (_, position) => position !== index,
                                        ),
                                    )
                                }
                            >
                                <Trash2 />
                            </IconButton>
                            <InputError
                                className="lg:col-span-6"
                                message={
                                    errors[`items.${index}.description`] ??
                                    errors[`items.${index}.quantity`] ??
                                    errors[`items.${index}.category`] ??
                                    errors[`items.${index}.line_total_minor`]
                                }
                            />
                        </div>
                    );
                })}
                <div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            form.setData('items', [
                                ...form.data.items,
                                {
                                    uuid: null,
                                    description: '',
                                    quantity: '',
                                    unit: '',
                                    category: '',
                                    price: '',
                                },
                            ])
                        }
                    >
                        <Plus />
                        Add item
                    </Button>
                </div>
                {linesTotal > 0 &&
                    typeof total === 'number' &&
                    linesTotal !== total && (
                        <p className="text-sm text-muted-foreground">
                            Item prices add up to{' '}
                            {formatMoney(linesTotal, currency)}. The total stays
                            as entered, since tax, discounts, and unlisted items
                            can explain the difference.
                        </p>
                    )}
            </fieldset>

            <label className="flex items-center gap-2 text-sm">
                <Checkbox
                    checked={form.data.needs_review}
                    onCheckedChange={(checked) =>
                        form.setData('needs_review', checked === true)
                    }
                />
                I still need to check this. Leave it out of spending and
                suggestions for now.
            </label>

            {errors.allow_duplicate && (
                <div className="grid gap-2">
                    <AlertError
                        title="This may be a duplicate"
                        errors={[errors.allow_duplicate]}
                    />
                    <div>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => submit(true)}
                        >
                            Save anyway
                        </Button>
                    </div>
                </div>
            )}

            <div>
                <Button type="submit" disabled={form.processing}>
                    {purchase ? 'Save changes' : 'Save purchase'}
                </Button>
            </div>
        </form>
    );
}
