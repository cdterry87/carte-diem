import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { execSync } from 'node:child_process';
import { register, uniqueEmail } from './helpers';

async function createList(
    page: import('@playwright/test').Page,
    title: string,
) {
    await page.goto('/lists');
    await page.getByLabel('New list name').fill(title);
    await page.getByRole('button', { name: 'Create list' }).click();
    await expect(page.getByRole('heading', { name: title })).toBeVisible();
}

test('suggestions come from real purchases and can be added or muted', async ({
    page,
}) => {
    await register(page, 'Sia Suggest', uniqueEmail('suggest'));
    await createList(page, 'Regulars');
    const listUrl = page.url();
    const quickAdd = page.getByRole('combobox', { name: 'Add an item' });

    for (const entry of ['oat milk', 'tea']) {
        await quickAdd.fill(entry);
        await quickAdd.press('Enter');
        await expect(quickAdd).toHaveValue('');
    }

    for (let trip = 0; trip < 2; trip++) {
        await page.goto(listUrl);
        await page.getByRole('button', { name: 'Start trip' }).click();
        await expect(page).toHaveURL(/trips\//);
        await page.getByRole('checkbox', { name: /oat milk/ }).click();
        await page
            .getByRole('checkbox', { name: /oat milk.*in the cart/ })
            .waitFor();
        await page.getByRole('button', { name: 'Complete trip' }).click();
        await page
            .getByRole('dialog')
            .getByRole('button', { name: 'Complete trip' })
            .click();
        await expect(
            page.getByText('Completed', { exact: true }),
        ).toBeVisible();
        await page.goto(listUrl);
        await expect(
            page.getByRole('heading', { name: 'Regulars' }),
        ).toBeVisible();

        if (trip === 0) {
            await page.getByRole('checkbox', { name: /oat milk/ }).click();
            await expect(
                page.getByRole('heading', { name: /Done/ }),
            ).toHaveCount(0);
        }
    }

    const panel = page.getByRole('region', { name: 'You may need' });
    await expect(
        panel.getByText('Purchased on 2 of your last 2 trips.'),
    ).toBeVisible();

    await panel.getByRole('button', { name: /Add oat milk/ }).click();
    await expect(panel.getByText('oat milk')).toHaveCount(0);

    await page.getByRole('checkbox', { name: /oat milk/ }).click();
    await expect(panel.getByText('oat milk')).toBeVisible();
    await panel
        .getByRole('button', { name: 'More options for oat milk' })
        .click();
    await page.getByRole('menuitem', { name: 'Stop suggesting this' }).click();
    await expect(panel.getByText('oat milk')).toHaveCount(0);

    await page.goto('/staples');
    await page.getByRole('button', { name: 'Suggest again: oat milk' }).click();
    await expect(
        page.getByRole('button', { name: 'Suggest again: oat milk' }),
    ).toHaveCount(0);
});

test('a due staple sends a reminder that shows in the notification feed', async ({
    page,
}) => {
    const email = uniqueEmail('remind');
    await register(page, 'Rhea Remind', email);

    await page.goto('/staples');
    await page.getByLabel('Staple name').fill('coffee beans');
    await page.getByLabel(/Remind every/).fill('1');
    await page.getByRole('button', { name: 'Add staple' }).click();
    await expect(page.getByText('coffee beans')).toBeVisible();

    execSync(
        `php artisan tinker --execute="App\\Models\\StapleRule::whereHas('product', fn (\\$q) => \\$q->where('scope_id', App\\Models\\User::where('email', '${email}')->value('id')))->get()->each(fn (\\$r) => \\$r->forceFill(['created_at' => now()->subDays(2)])->save());"`,
        { stdio: 'ignore' },
    );
    execSync('php artisan reminders:send', { stdio: 'ignore' });
    execSync('php artisan queue:work --stop-when-empty', { stdio: 'ignore' });

    await page.goto('/notifications');
    await expect(page.getByText('You may need: coffee beans.')).toBeVisible();
    await expect(page.getByRole('link', { name: /1 unread/ })).toBeVisible();

    await page
        .getByRole('button', { name: /You may need: coffee beans/ })
        .click();
    await expect(page).toHaveURL(/staples/);
    await expect(
        page.getByRole('link', { name: 'Notifications', exact: true }),
    ).toBeVisible();

    await page.goto('/settings/notifications');
    await page.getByRole('checkbox', { name: 'List changes by email' }).click();
    await page.getByLabel('From').fill('22:00');
    await page.getByLabel('Until').fill('07:00');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByText('Notification settings saved.')).toBeVisible();
    await page.reload();
    await expect(
        page.getByRole('checkbox', { name: 'List changes by email' }),
    ).toBeChecked();
    await expect(page.getByLabel('From')).toHaveValue('22:00');
});

test('store aisle order changes how a list is sorted', async ({ page }) => {
    await register(page, 'Ada Aisle', uniqueEmail('aisle'));
    await page.goto('/organize');
    await page.getByLabel('Store name').fill('Corner Market');
    await page.getByRole('button', { name: 'Add store' }).click();
    await page
        .getByRole('button', { name: 'Set aisle order for Corner Market' })
        .click();
    await page.getByRole('button', { name: 'Move Dairy & Eggs up' }).click();
    await page.getByRole('button', { name: 'Save order' }).click();
    await expect(page.getByText('Aisle order saved.')).toBeVisible();

    await createList(page, 'Aisle list');
    const quickAdd = page.getByRole('combobox', { name: 'Add an item' });

    for (const entry of ['apples', 'milk']) {
        await quickAdd.fill(entry);
        await quickAdd.press('Enter');
        await expect(quickAdd).toHaveValue('');
    }

    await expect(page.getByRole('checkbox').first()).toHaveAccessibleName(
        /apples/,
    );

    await page.getByRole('button', { name: 'Edit list details' }).click();
    await page.getByRole('combobox', { name: 'Default store' }).click();
    await page.getByRole('option', { name: 'Corner Market' }).click();
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByRole('checkbox').first()).toHaveAccessibleName(
        /milk/,
    );
});

test('staples, notifications, and their settings have no automatically detectable accessibility violations', async ({
    page,
}) => {
    await register(page, 'Ari Access', uniqueEmail('suggestions-a11y'));

    for (const path of [
        '/staples',
        '/notifications',
        '/settings/notifications',
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
