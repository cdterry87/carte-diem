export function formatDate(
    iso: string | null | undefined,
    timeZone?: string,
): string {
    if (!iso) {
        return '';
    }

    const options: Intl.DateTimeFormatOptions = {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    };

    try {
        return new Date(iso).toLocaleDateString(undefined, {
            ...options,
            timeZone,
        });
    } catch {
        // A saved zone this browser does not know falls back to the device's own.
        return new Date(iso).toLocaleDateString(undefined, options);
    }
}

/** Trims trailing zeros from a fixed-scale decimal: "2.000" becomes "2". */
export function formatQuantity(quantity: string | null | undefined): string {
    if (quantity === null || quantity === undefined) {
        return '';
    }

    return String(Number(quantity));
}

export function formatAmount(
    quantity: string | null | undefined,
    unit: string | null | undefined,
): string {
    return [formatQuantity(quantity), unit ?? ''].filter(Boolean).join(' ');
}

function minorDigits(currency: string): number {
    return (
        new Intl.NumberFormat('en', {
            style: 'currency',
            currency,
        }).resolvedOptions().maximumFractionDigits ?? 2
    );
}

export function formatMoney(minor: number, currency: string): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
    }).format(minor / 10 ** minorDigits(currency));
}

/** The value for an amount input: 1250 becomes "12.50". */
export function minorToInput(
    minor: number | null | undefined,
    currency: string,
): string {
    if (minor === null || minor === undefined) {
        return '';
    }

    const digits = minorDigits(currency);

    return digits === 0
        ? String(minor)
        : `${Math.floor(minor / 10 ** digits)}.${String(minor % 10 ** digits).padStart(digits, '0')}`;
}

/**
 * Reads an amount as text, so no floating point is involved: "12.5" becomes 1250.
 * Empty input is null, and anything that is not an amount is undefined.
 */
export function parseMoney(
    input: string,
    currency: string,
): number | null | undefined {
    const text = input.replace(/[\s,$]/g, '');

    if (text === '') {
        return null;
    }

    const digits = minorDigits(currency);
    const match = text.match(/^(\d+)(?:\.(\d*))?$/);

    if (!match || (match[2]?.length ?? 0) > digits) {
        return undefined;
    }

    return Number(`${match[1]}${(match[2] ?? '').padEnd(digits, '0')}`);
}

/** "2026-11" as "November 2026". */
export function monthLabel(
    month: string,
    format: Intl.DateTimeFormatOptions = { month: 'long', year: 'numeric' },
): string {
    const [year, number] = month.split('-').map(Number);

    return new Date(year, number - 1).toLocaleDateString(undefined, format);
}

/** "each" for single items, otherwise "per lb". */
export function perUnit(unit: string): string {
    return unit === 'each' ? 'each' : `per ${unit}`;
}
