import { expect, test } from '@playwright/test';
import { register, uniqueEmail } from './helpers';

test.use({ timezoneId: 'America/Chicago' });

test('registration detects the time zone and settings can change it', async ({
    page,
}) => {
    await register(page, 'Tess Zone', uniqueEmail('zone'));
    await page.goto('/settings/profile');

    const select = page.getByRole('combobox', { name: 'Time zone' });

    await expect(select).toHaveText('America/Chicago');
    await select.click();
    await page.getByRole('option', { name: 'Asia/Tokyo' }).click();
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByText('Profile updated.')).toBeVisible();

    await page.reload();
    await expect(select).toHaveText('Asia/Tokyo');
});

test('dates display in the saved time zone, not the device zone', async ({
    page,
}) => {
    await register(page, 'Dana Date', uniqueEmail('date'));
    await page.goto('/lists');
    await page.getByLabel('New list name').fill('Zone list');
    await page.getByRole('button', { name: 'Create list' }).click();
    await page.getByRole('combobox', { name: 'Add an item' }).fill('milk');
    await page.getByRole('combobox', { name: 'Add an item' }).press('Enter');
    await expect(page.getByText('milk')).toBeVisible();
    await page.getByRole('button', { name: 'Start trip' }).click();
    await expect(page).toHaveURL(/trips\//);
    const tripUrl = page.url();

    const expected = (timeZone: string) =>
        page.evaluate(
            (zone) =>
                new Date().toLocaleDateString(undefined, {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                    timeZone: zone,
                }),
            timeZone,
        );

    await expect(
        page.getByText(`Started ${await expected('America/Chicago')}`),
    ).toBeVisible();

    await page.goto('/settings/profile');
    await page.getByRole('combobox', { name: 'Time zone' }).click();
    await page.getByRole('option', { name: 'Pacific/Kiritimati' }).click();
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByText('Profile updated.')).toBeVisible();

    // Kiritimati (UTC+14) is ahead of Chicago, so the formatted day can differ.
    await page.goto(tripUrl);
    await expect(
        page.getByText(`Started ${await expected('Pacific/Kiritimati')}`),
    ).toBeVisible();
});
