import { execSync } from 'node:child_process';
import { expect, test } from '@playwright/test';
import { register, uniqueEmail } from './helpers';

test.skip(!!process.env.E2E_NO_LIVE, 'Live updates are not running here');

test('household members see each other in real time, and outsiders cannot subscribe', async ({
    page,
    browser,
    baseURL,
}) => {
    const memberEmail = uniqueEmail('live-member');
    const householdName = `Live home ${Date.now()}`;

    await register(page, 'Lena Live', uniqueEmail('live-owner'));
    await page.goto('/households');
    await page.getByLabel('New household name').fill(householdName);
    await page.getByRole('button', { name: 'Create household' }).click();
    await expect(
        page.getByRole('heading', { name: householdName, exact: true }),
    ).toBeVisible();

    await page.goto('/lists');
    await page.getByLabel('New list name').fill('Live list');
    await page.getByRole('combobox', { name: 'List owner' }).click();
    await page.getByRole('option', { name: householdName }).click();
    await page.getByRole('button', { name: 'Create list' }).click();
    await expect(
        page.getByRole('heading', { name: 'Live list' }),
    ).toBeVisible();
    const listUrl = page.url();
    const listUuid = listUrl.split('/').pop();

    const memberContext = await browser.newContext({ baseURL });
    const member = await memberContext.newPage();
    await register(member, 'Mia Member', memberEmail);
    execSync(
        `php artisan tinker --execute="App\\Models\\Household::where('name','${householdName}')->first()->addMember(App\\Models\\User::where('email','${memberEmail}')->first(), App\\Enums\\HouseholdRole::Member);"`,
        { stdio: 'ignore' },
    );
    await member.goto(listUrl);
    await expect(
        member.getByRole('heading', { name: 'Live list' }),
    ).toBeVisible();
    await member.waitForLoadState('networkidle');

    const quickAdd = page.getByRole('combobox', { name: 'Add an item' });
    await quickAdd.fill('live milk');
    await quickAdd.press('Enter');

    await expect(member.getByText('live milk')).toBeVisible({ timeout: 10000 });

    await member.getByRole('button', { name: 'Start trip' }).click();
    await expect(member).toHaveURL(/trips\//);
    const tripUrl = member.url();
    await member.waitForLoadState('networkidle');

    await page.goto(tripUrl);
    await page.waitForLoadState('networkidle');
    await page.getByRole('checkbox', { name: /live milk/ }).click();
    await expect(member.getByText('1 of 1 in the cart')).toBeVisible({
        timeout: 10000,
    });

    const authorize = (target: typeof page, channel: string) =>
        target.evaluate(async (channelName) => {
            const token = decodeURIComponent(
                document.cookie
                    .split('; ')
                    .find((entry) => entry.startsWith('XSRF-TOKEN='))
                    ?.split('=')[1] ?? '',
            );
            const response = await fetch('/broadcasting/auth', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': token,
                },
                body: JSON.stringify({
                    channel_name: channelName,
                    socket_id: '1234.5678',
                }),
            });

            return response.status;
        }, channel);

    expect(await authorize(member, `private-list.${listUuid}`)).toBe(200);

    const outsiderContext = await browser.newContext({ baseURL });
    const outsider = await outsiderContext.newPage();
    await register(outsider, 'Otis Out', uniqueEmail('live-out'));
    expect(await authorize(outsider, `private-list.${listUuid}`)).toBe(403);

    await outsiderContext.close();
    await memberContext.close();
});
