import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { mailLink, register, runQueue, uniqueEmail } from './helpers';

test('household shopping from registration to history', async ({
    page,
    browser,
    baseURL,
}) => {
    const ownerEmail = uniqueEmail('owner');
    const memberEmail = uniqueEmail('member');

    await test.step('new user creates a household', async () => {
        await register(page, 'Olive Owner', ownerEmail);
        await page.goto('/households');
        await page.getByLabel('New household name').fill('Test home');
        await page.getByRole('button', { name: 'Create household' }).click();
        await expect(
            page.getByRole('heading', { name: 'Test home' }),
        ).toBeVisible();
    });

    await test.step('owner creates a household list and adds items', async () => {
        await page.goto('/lists');
        await page.getByLabel('New list name').fill('Weekly shop');
        await page.getByRole('combobox', { name: 'List owner' }).click();
        await page.getByRole('option', { name: 'Test home' }).click();
        await page.getByRole('button', { name: 'Create list' }).click();
        await expect(
            page.getByRole('heading', { name: 'Weekly shop' }),
        ).toBeVisible();

        const quickAdd = page.getByRole('combobox', { name: 'Add an item' });

        for (const entry of ['3 lb apples', 'milk', 'mystery widget']) {
            await quickAdd.fill(entry);
            await quickAdd.press('Enter');
            await expect(quickAdd).toHaveValue('');
        }

        await expect(page.getByText('apples')).toBeVisible();
        await expect(page.getByText('3 lb')).toBeVisible();
        await expect(
            page.getByRole('heading', { name: 'Produce' }),
        ).toBeVisible();
        await expect(
            page.getByRole('heading', { name: 'Dairy & Eggs' }),
        ).toBeVisible();
    });

    await test.step('checking directly moves an item below the needed items', async () => {
        await page.getByRole('checkbox', { name: /mystery widget/ }).click();
        await expect(
            page.getByRole('heading', { name: /Done \(1\)/ }),
        ).toBeVisible();
        await page.getByRole('checkbox', { name: /mystery widget/ }).click();
        await expect(page.getByRole('heading', { name: /Done/ })).toHaveCount(
            0,
        );
    });

    const listUrl = page.url();

    await test.step('owner invites a member by email', async () => {
        await page.goto('/households');
        await page.getByRole('link', { name: /Test home/ }).click();
        await page.getByLabel('Email address').fill(memberEmail);
        await page.getByRole('button', { name: 'Send invitation' }).click();
        await expect(page.getByText(`${memberEmail}`)).toBeVisible();
        runQueue();
    });

    const memberContext = await browser.newContext({ baseURL });
    const member = await memberContext.newPage();

    await test.step('invited person joins and sees the list', async () => {
        await register(member, 'Max Member', memberEmail);
        await member.goto(
            await mailLink(
                member,
                memberEmail,
                /https?:\/\/\S*\/invitations\/\S+/,
            ),
        );
        await member.getByRole('button', { name: 'Accept' }).click();
        await expect(
            member.getByRole('heading', { name: 'Test home', exact: true }),
        ).toBeVisible();
        await member.goto(listUrl);
        await expect(
            member.getByRole('heading', { name: 'Weekly shop' }),
        ).toBeVisible();
    });

    await test.step('member runs a trip with a partial purchase', async () => {
        await member.getByRole('button', { name: 'Start trip' }).click();
        await expect(member).toHaveURL(/trips\//);
        await member.getByRole('checkbox', { name: /apples/ }).click();
        await member.getByLabel('Bought').fill('1');
        await member.getByLabel('Bought').blur();
        await member.getByRole('checkbox', { name: /milk/ }).click();
        await expect(member.getByText('2 of 3 in the cart')).toBeVisible();
    });

    await test.step('items added during the trip wait for explicit inclusion', async () => {
        const quickAdd = page.getByRole('combobox', { name: 'Add an item' });

        await page.goto(listUrl);
        await quickAdd.fill('bread');
        await quickAdd.press('Enter');
        await expect(quickAdd).toHaveValue('');
        await member.reload();
        await expect(
            member.getByRole('heading', { name: 'Added since trip began' }),
        ).toBeVisible();
    });

    await test.step('completing keeps unpurchased needs and records history', async () => {
        await member.getByRole('button', { name: 'Complete trip' }).click();
        await member
            .getByRole('dialog')
            .getByRole('button', { name: 'Complete trip' })
            .click();
        await expect(
            member.getByText('Completed', { exact: true }),
        ).toBeVisible();

        await page.goto(listUrl);
        await expect(page.getByText('2 lb')).toBeVisible();
        await expect(
            page.getByRole('heading', { name: /Done \(1\)/ }),
        ).toBeVisible();
        await expect(
            page.getByRole('checkbox', { name: /mystery widget as done/ }),
        ).toBeVisible();

        await page.goto('/history');
        await page.getByRole('link', { name: /Weekly shop/ }).click();
        await page.getByRole('link', { name: 'View purchase' }).click();
        await expect(page.getByText('apples')).toBeVisible();
        await expect(page.getByText('milk')).toBeVisible();
        await expect(page.getByText('mystery widget')).toHaveCount(0);
    });

    await test.step('an outsider cannot open the list', async () => {
        const outsiderContext = await browser.newContext({ baseURL });
        const outsider = await outsiderContext.newPage();

        await register(outsider, 'Otto Outsider', uniqueEmail('outsider'));
        const response = await outsider.goto(listUrl);

        expect(response?.status()).toBe(404);
        await outsiderContext.close();
    });

    await memberContext.close();
});

test('main pages have no automatically detectable accessibility violations', async ({
    page,
}) => {
    await register(page, 'Ada Access', uniqueEmail('a11y'));

    await page.goto('/lists');
    await page.getByLabel('New list name').fill('Accessible list');
    await page.getByRole('button', { name: 'Create list' }).click();
    await expect(
        page.getByRole('heading', { name: 'Accessible list' }),
    ).toBeVisible();
    const quickAdd = page.getByRole('combobox', { name: 'Add an item' });
    await quickAdd.fill('milk');
    await quickAdd.press('Enter');
    await expect(page.getByText('milk')).toBeVisible();
    const listUrl = page.url();

    await page.getByRole('button', { name: 'Start trip' }).click();
    await expect(page).toHaveURL(/trips\//);
    const tripUrl = page.url();

    for (const path of [
        '/dashboard',
        '/lists',
        listUrl,
        tripUrl,
        '/history',
        '/households',
        '/organize',
    ]) {
        await page.goto(path);
        await page.waitForLoadState('networkidle');
        const results = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
            .analyze();

        expect(
            results.violations.map(
                (violation) =>
                    `${path}: ${violation.id} ${String(violation.nodes[0]?.target)}`,
            ),
        ).toEqual([]);
    }
});
