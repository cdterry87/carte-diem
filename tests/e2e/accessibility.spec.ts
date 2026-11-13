import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import { expect, test } from '@playwright/test';
import { addItem, createList, register, uniqueEmail } from './helpers';

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

async function violations(page: Page, label: string): Promise<string[]> {
    await page.waitForLoadState('networkidle');
    const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();

    return results.violations.flatMap((violation) =>
        violation.nodes.map(
            (node) => `${label}: ${violation.id} ${String(node.target)}`,
        ),
    );
}

/** Contrast can only be judged once the dialog has finished fading in. */
async function dialogSettled(page: Page): Promise<void> {
    const dialog = page.getByRole('dialog');

    await dialog.waitFor();
    await dialog.evaluate((element) =>
        Promise.all(
            element
                .getAnimations({ subtree: true })
                .map((animation) => animation.finished),
        ),
    );
}

for (const scheme of ['light', 'dark'] as const) {
    test(`every page passes automated checks in ${scheme} mode, without sideways scrolling`, async ({
        page,
    }) => {
        test.setTimeout(90000);
        await page.emulateMedia({ colorScheme: scheme });
        await register(page, 'Ada Access', uniqueEmail(`scan-${scheme}`));

        const listUrl = await createList(page, 'Scan list');

        await addItem(page, '2 lb apples');
        await addItem(page, 'milk');
        await page.getByRole('checkbox', { name: /milk/ }).click();
        await expect(page.getByRole('heading', { name: /Done/ })).toBeVisible();
        await page.getByRole('button', { name: 'Start trip' }).click();
        await expect(page).toHaveURL(/trips\//);
        const tripUrl = page.url();

        await page.getByRole('checkbox', { name: /apples/ }).click();
        await page.getByLabel('Bought').waitFor();

        const found: string[] = [];
        const overflowing: string[] = [];

        for (const path of [
            '/',
            '/dashboard',
            '/lists',
            listUrl,
            tripUrl,
            '/staples',
            '/history',
            '/households',
            '/organize',
            '/notifications',
            '/settings/profile',
            '/settings/notifications',
            '/settings/appearance',
            '/privacy',
        ]) {
            await page.goto(path);
            found.push(...(await violations(page, path)));

            if (
                await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth >
                        window.innerWidth,
                )
            ) {
                overflowing.push(path);
            }
        }

        expect(found).toEqual([]);
        expect(overflowing).toEqual([]);
    });

    test(`guest pages and dialogs pass automated checks in ${scheme} mode`, async ({
        page,
    }) => {
        await page.emulateMedia({ colorScheme: scheme });

        const found: string[] = [];

        for (const path of ['/login', '/register', '/forgot-password']) {
            await page.goto(path);
            found.push(...(await violations(page, path)));
        }

        await register(page, 'Dia Dialog', uniqueEmail(`dialog-${scheme}`));
        await createList(page, 'Dialog list');
        await addItem(page, 'milk');

        await page.getByRole('button', { name: 'Share list' }).click();
        await dialogSettled(page);
        found.push(...(await violations(page, 'share dialog')));
        await page.keyboard.press('Escape');
        await expect(page.getByRole('dialog')).toHaveCount(0);

        await page.getByRole('button', { name: 'Edit milk' }).click();
        await dialogSettled(page);
        found.push(...(await violations(page, 'item dialog')));
        await page.keyboard.press('Escape');

        expect(found).toEqual([]);
    });
}

test('the core flow works by keyboard alone', async ({ page, isMobile }) => {
    test.skip(isMobile, 'Keyboard flows are for hardware keyboards');

    await register(page, 'Kay Keyboard', uniqueEmail('keyboard'));
    await createList(page, 'Keyboard list');

    const quickAdd = page.getByRole('combobox', { name: 'Add an item' });

    await quickAdd.focus();
    await page.keyboard.type('2 lb apples');
    await page.keyboard.press('Enter');
    await expect(page.getByText('2 lb')).toBeVisible();
    await expect(quickAdd).toBeFocused();

    await page.keyboard.type('milk');
    await page.keyboard.press('Enter');
    await expect(quickAdd).toHaveValue('');

    await page.keyboard.type('app');
    await page.getByRole('option', { name: /apples/ }).waitFor();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('listbox')).toHaveCount(0);
    await quickAdd.fill('');

    const checkbox = page.getByRole('checkbox', { name: /milk/ });
    await checkbox.focus();
    await page.keyboard.press('Space');
    await expect(
        page.getByRole('heading', { name: /Done \(1\)/ }),
    ).toBeVisible();

    const del = page.getByRole('button', { name: 'Delete list' });
    await del.focus();
    await page.keyboard.press('Enter');
    // Let the dialog finish opening, as a person would, before using its keys.
    await dialogSettled(page);
    await page.keyboard.press('Tab');
    expect(
        await page.evaluate(
            () => document.activeElement?.closest('[role="dialog"]') !== null,
        ),
    ).toBe(true);
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await expect(del).toBeFocused();

    const start = page.getByRole('button', { name: 'Start trip' });
    await start.focus();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/trips\//);

    await page.getByRole('checkbox', { name: /apples/ }).focus();
    await page.keyboard.press('Space');
    await expect(page.getByText('1 of 1 in the cart')).toBeVisible();

    const complete = page.getByRole('button', { name: 'Complete trip' });
    await complete.focus();
    await page.keyboard.press('Enter');
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Complete trip' })
        .focus();
    await page.keyboard.press('Enter');
    await expect(page.getByText('Completed', { exact: true })).toBeVisible();
});

test('the home page skip link and focus styles work', async ({
    page,
    isMobile,
}) => {
    test.skip(isMobile, 'Keyboard flows are for hardware keyboards');

    await page.goto('/');
    await page.keyboard.press('Tab');

    const skip = page.getByRole('link', { name: 'Skip to content' });

    await expect(skip).toBeFocused();
    await expect(skip).toBeInViewport();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/#main$/);

    const cta = page
        .getByRole('link', { name: 'Create your first list' })
        .first();

    await cta.focus();
    const shadow = await cta.evaluate(
        (element) => getComputedStyle(element).boxShadow,
    );

    expect(shadow).not.toBe('none');
});

test('authenticated pages load without console errors', async ({ page }) => {
    const errors: string[] = [];

    page.on('console', (message) => {
        if (message.type() === 'error') {
            errors.push(message.text());
        }
    });

    await register(page, 'Cleo Console', uniqueEmail('console'));
    await createList(page, 'Console list');

    for (const path of [
        '/dashboard',
        '/lists',
        '/staples',
        '/notifications',
        '/settings/profile',
    ]) {
        await page.goto(path);
        await page.waitForLoadState('networkidle');
    }

    expect(errors).toEqual([]);
});
