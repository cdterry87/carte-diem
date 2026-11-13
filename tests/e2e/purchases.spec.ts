import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { register, uniqueEmail } from './helpers';

async function addPurchase(
    page: import('@playwright/test').Page,
    merchant: string,
    total: string,
) {
    await page.goto('/purchases/create');
    await page.getByLabel('Store or merchant').fill(merchant);
    await page.getByLabel('Total', { exact: true }).fill(total);
    await page.getByRole('button', { name: 'Save purchase' }).click();
    await expect(page.getByRole('heading', { name: merchant })).toBeVisible();
}

test('adds a purchase with items, corrects it, and spending follows', async ({
    page,
}) => {
    await register(page, 'Pat Purchase', uniqueEmail('purchase'));

    await page.goto('/purchases/create');
    await page.getByLabel('Store or merchant').fill('Corner Market');
    await page.getByLabel('Tax (optional)').fill('1.25');
    await page.getByLabel('Total', { exact: true }).fill('not money');
    await page.getByRole('button', { name: 'Add item' }).click();
    await page.getByLabel('Item 1 name').fill('Oat milk');
    await page.getByLabel('Item 1 price').fill('4.50');
    await page.getByRole('button', { name: 'Save purchase' }).click();
    await expect(page.getByText('Enter an amount like 12.50.')).toBeVisible();

    await page.getByLabel('Total', { exact: true }).fill('12.50');
    await expect(page.getByText(/Item prices add up to \$4\.50/)).toBeVisible();
    await page.getByRole('button', { name: 'Save purchase' }).click();

    await expect(
        page.getByRole('heading', { name: 'Corner Market' }),
    ).toBeVisible();
    await expect(page.getByText('$12.50')).toBeVisible();
    await expect(page.getByText('Oat milk')).toBeVisible();

    await page.getByRole('link', { name: 'Edit' }).click();
    await expect(page.getByLabel('Total', { exact: true })).toHaveValue(
        '12.50',
    );
    await page.getByLabel('Total', { exact: true }).fill('20');
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText('$20.00')).toBeVisible();

    await page.goto('/spending');
    const spent = page.getByRole('region', { name: 'Spending in USD' });
    await expect(spent.getByText('across 1 purchase')).toBeVisible();
    await expect(spent.getByText('$20.00').first()).toBeVisible();
    await expect(spent.getByText('$12.50')).toHaveCount(0);
    await expect(
        spent.getByRole('rowheader', { name: 'Corner Market' }),
    ).toBeVisible();
});

test('warns about a likely duplicate and can save anyway', async ({ page }) => {
    await register(page, 'Dee Duplicate', uniqueEmail('duplicate'));
    await addPurchase(page, 'Bakery', '9.00');

    await page.goto('/purchases/create');
    await page.getByLabel('Store or merchant').fill('Bakery');
    await page.getByLabel('Total', { exact: true }).fill('9.00');
    await page.getByRole('button', { name: 'Save purchase' }).click();
    await expect(page.getByText('This may be a duplicate')).toBeVisible();

    await page.getByRole('button', { name: 'Save anyway' }).click();
    await expect(page.getByRole('heading', { name: 'Bakery' })).toBeVisible();

    await page.goto('/purchases');
    await expect(page.getByRole('link', { name: /Bakery/ })).toHaveCount(2);
});

test('a completed trip becomes a purchase, which then takes an amount', async ({
    page,
}) => {
    await register(page, 'Tia Trip', uniqueEmail('trip-amount'));
    await page.goto('/lists');
    await page.getByLabel('New list name').fill('Weekly');
    await page.getByRole('button', { name: 'Create list' }).click();
    await expect(page.getByRole('heading', { name: 'Weekly' })).toBeVisible();
    const quickAdd = page.getByRole('combobox', { name: 'Add an item' });
    await quickAdd.fill('tea');
    await quickAdd.press('Enter');
    await expect(quickAdd).toHaveValue('');
    await page.getByRole('button', { name: 'Start trip' }).click();
    await expect(page).toHaveURL(/trips\//);

    await page.getByRole('checkbox', { name: /tea/ }).click();
    await page.getByRole('checkbox', { name: /tea.*in the cart/ }).waitFor();
    await page.getByRole('button', { name: 'Complete trip' }).click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Complete trip' })
        .click();
    await expect(page.getByText('Completed', { exact: true })).toBeVisible();

    await page.getByRole('link', { name: 'View purchase' }).click();
    await expect(page.getByText('No amount yet')).toBeVisible();

    await page.getByRole('link', { name: 'Add amount' }).click();
    await page.getByLabel('Total', { exact: true }).fill('3.50');
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText('$3.50')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Edit' })).toBeVisible();
});

test('deletes and restores a purchase', async ({ page }) => {
    await register(page, 'Rob Restore', uniqueEmail('restore'));
    await addPurchase(page, 'Hardware', '15.00');

    await page.getByRole('button', { name: 'Delete' }).click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Delete purchase' })
        .click();
    await expect(page.getByText('Recently deleted')).toBeVisible();

    await page.goto('/spending');
    await expect(page.getByText('No spending yet')).toBeVisible();

    await page.goto('/purchases');
    await page.getByRole('button', { name: 'Restore Hardware' }).click();
    await expect(page.getByText('Purchase restored.')).toBeVisible();
    await expect(page.getByRole('link', { name: /Hardware/ })).toBeVisible();
});

test('purchase pages have no accessibility violations', async ({ page }) => {
    await register(page, 'Ada Access', uniqueEmail('a11y-purchases'));
    await addPurchase(page, 'Pharmacy', '11.00');
    const purchaseUrl = page.url();

    for (const url of [
        '/purchases',
        '/purchases/create',
        purchaseUrl,
        `${purchaseUrl}/edit`,
        '/spending',
    ]) {
        await page.goto(url);
        await page.waitForLoadState('networkidle');
        const results = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
            .analyze();
        expect(results.violations, url).toEqual([]);
    }
});

test('enters prices and a total during a trip, and they land on the purchase', async ({
    page,
}) => {
    await register(page, 'Amy Amount', uniqueEmail('trip-amount'));
    await page.goto('/lists');
    await page.getByLabel('New list name').fill('Amounts');
    await page.getByRole('button', { name: 'Create list' }).click();
    await expect(page.getByRole('heading', { name: 'Amounts' })).toBeVisible();
    const quickAdd = page.getByRole('combobox', { name: 'Add an item' });
    await quickAdd.fill('tea');
    await quickAdd.press('Enter');
    await expect(quickAdd).toHaveValue('');
    await page.getByRole('button', { name: 'Start trip' }).click();
    await expect(page).toHaveURL(/trips\//);

    await page.getByRole('checkbox', { name: /tea/ }).click();
    await page.getByRole('checkbox', { name: /tea.*in the cart/ }).waitFor();
    const price = page.getByLabel('Price of tea');
    await price.fill('oops');
    await price.blur();
    await expect(page.getByText('Enter an amount like 12.50.')).toBeVisible();
    await price.fill('4.50');
    await price.blur();
    await expect(page.getByText(/add up to \$4\.50/)).toBeVisible();

    const total = page.getByLabel('Total paid');
    const saved = page.waitForResponse(
        (response) =>
            response.url().endsWith('/amount') && response.status() < 400,
    );
    await total.fill('5');
    await total.blur();
    await saved;

    await page.getByRole('button', { name: 'Complete trip' }).click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Complete trip' })
        .click();
    await page.getByRole('link', { name: 'View purchase' }).click();
    await expect(page.getByText('$5.00')).toBeVisible();
    await expect(page.getByText('$4.50')).toBeVisible();
});

test('moves a purchase to a household', async ({ page }) => {
    await register(page, 'Mo Move', uniqueEmail('move'));
    await page.goto('/households');
    await page
        .getByLabel(/household name/i)
        .first()
        .fill('Move Home');
    await page
        .getByRole('button', { name: /create/i })
        .first()
        .click();
    await expect(page.getByText('Move Home').first()).toBeVisible();

    await addPurchase(page, 'Garden Shop', '30.00');
    await page.getByRole('button', { name: 'Move', exact: true }).click();
    await page.getByLabel('Move to').selectOption({ label: 'Move Home' });
    await page.getByRole('button', { name: 'Move purchase' }).click();
    await expect(page.getByText('Purchase moved.')).toBeVisible();
    await expect(page.getByText('Move Home').first()).toBeVisible();

    await page.goto('/purchases');
    await expect(page.getByRole('link', { name: /Garden Shop/ })).toHaveCount(
        0,
    );
    await page.getByRole('link', { name: 'Move Home' }).click();
    await expect(page.getByRole('link', { name: /Garden Shop/ })).toBeVisible();
});

test('shows a monthly chart with details on focus', async ({ page }) => {
    await register(page, 'Cy Chart', uniqueEmail('chart'));
    await addPurchase(page, 'Chart Market', '87.00');

    await page.goto('/spending?months=3');
    const month = new Date().toLocaleDateString(undefined, {
        month: 'long',
        year: 'numeric',
    });
    const bar = page.getByRole('img', { name: `${month}: $87.00, 1 purchase` });
    await expect(bar).toBeVisible();
    await bar.focus();
    await expect(page.locator('figcaption')).toContainText(`$87.00 ${month}`);
});

test('shows spending by category and a product price history', async ({
    page,
}) => {
    await register(page, 'Pat Prices', uniqueEmail('prices'));

    for (const [merchant, price] of [
        ['Corner Market', '4.00'],
        ['Big Grocer', '5.00'],
    ]) {
        await page.goto('/purchases/create');
        await page.getByLabel('Store or merchant').fill(merchant);
        await page.getByLabel('Total', { exact: true }).fill(price);
        await page.getByRole('button', { name: 'Add item' }).click();
        await page.getByLabel('Item 1 name').fill('Oat milk');
        await page.getByLabel('Item 1 price').fill(price);
        await page.getByRole('button', { name: 'Save purchase' }).click();
        await expect(
            page.getByRole('heading', { name: merchant }),
        ).toBeVisible();
    }

    await page.goto('/spending');
    await expect(
        page.getByRole('table', { name: 'By category' }),
    ).toBeVisible();
    await expect(
        page.getByText(/Categories use \$9\.00 in item prices/),
    ).toBeVisible();

    await page.getByRole('link', { name: 'Price history' }).click();
    await page.getByRole('link', { name: 'Oat milk' }).click();
    await expect(page.getByRole('heading', { name: 'Oat milk' })).toBeVisible();
    await expect(
        page.getByRole('table', { name: 'By store, lowest average first' }),
    ).toContainText('Corner Market');
    await expect(page.locator('figcaption')).toContainText('Price each');

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .analyze();
    expect(results.violations).toEqual([]);
});
