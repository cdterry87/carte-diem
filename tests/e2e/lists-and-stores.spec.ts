import { expect, test } from '@playwright/test';
import { register, uniqueEmail } from './helpers';

test('restores a deleted list', async ({ page }) => {
    await register(page, 'Rita Restore', uniqueEmail('restore'));
    await page.goto('/lists');
    await page.getByLabel('New list name').fill('Oops list');
    await page.getByRole('button', { name: 'Create list' }).click();
    await page.getByRole('button', { name: 'Delete list' }).click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Delete list' })
        .click();
    await expect(page).toHaveURL(/\/lists$/);

    await page.goto('/lists?status=deleted');
    await page.getByRole('button', { name: 'Restore Oops list' }).click();
    await expect(
        page.getByRole('heading', { name: 'Oops list' }),
    ).toBeVisible();
});

test('reorders items and records trip details', async ({ page, isMobile }) => {
    await register(page, 'Rae Reorder', uniqueEmail('reorder'));
    await page.goto('/lists');
    await page.getByLabel('New list name').fill('Order list');
    await page.getByRole('button', { name: 'Create list' }).click();

    const quickAdd = page.getByRole('combobox', { name: 'Add an item' });

    for (const entry of ['milk', 'cheese']) {
        await quickAdd.fill(entry);
        await quickAdd.press('Enter');
        await expect(quickAdd).toHaveValue('');
    }

    if (isMobile) {
        await expect(
            page.getByRole('button', { name: 'Move cheese up' }),
        ).toBeHidden();
        await page.getByRole('button', { name: 'Reorder' }).click();
    }

    await page.getByRole('button', { name: 'Move cheese up' }).click();
    await expect(page.getByRole('checkbox').first()).toHaveAccessibleName(
        /cheese/,
    );

    if (isMobile) {
        await page.getByRole('button', { name: 'Done reordering' }).click();
    }

    await page.getByRole('button', { name: 'Start trip' }).click();
    await expect(page).toHaveURL(/trips\//);
    await page.getByRole('checkbox', { name: /milk/ }).click();
    await page.getByLabel('Bought').fill('2');
    await page.getByLabel('Note for milk').fill('store brand');
    await page.getByLabel('Note for milk').blur();
    await page.waitForLoadState('networkidle');
    await page.reload();
    await expect(page.getByLabel('Bought')).toHaveValue('2');
    await expect(page.getByLabel('Note for milk')).toHaveValue('store brand');
});

test('renames a store and a custom category', async ({ page }) => {
    await register(page, 'Rory Rename', uniqueEmail('rename'));
    await page.goto('/organize');
    await page.getByLabel('Store name').fill('Old Market');
    await page.getByRole('button', { name: 'Add store' }).click();
    await page.getByRole('button', { name: 'Edit Old Market' }).click();
    await page.getByLabel('New name for Old Market').fill('New Market');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(
        page.getByRole('button', { name: 'Edit New Market' }),
    ).toBeVisible();

    await page.getByLabel('Category name').fill('Garden');
    await page.getByRole('button', { name: 'Add category' }).click();
    await page.getByRole('button', { name: 'Edit Garden' }).click();
    await page.getByLabel('New name for Garden').fill('Yard');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByRole('button', { name: 'Edit Yard' })).toBeVisible();
});
