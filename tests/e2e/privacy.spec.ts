import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { register, uniqueEmail } from './helpers';

test('the privacy policy is public, linked, and readable', async ({ page }) => {
    await page.goto('/');
    await page
        .getByRole('navigation', { name: 'Legal' })
        .getByRole('link', { name: 'Privacy Policy' })
        .click();
    await expect(page).toHaveURL(/privacy/);
    await expect(
        page.getByRole('heading', { level: 1, name: 'Privacy Policy' }),
    ).toBeVisible();

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(results.violations.map((violation) => violation.id)).toEqual([]);
});

test('a user deletes their account and can no longer sign in', async ({
    page,
}) => {
    const email = uniqueEmail('delete');

    await register(page, 'Dee Delete', email);
    await page.goto('/settings/profile');
    await page.getByRole('button', { name: 'Delete account' }).click();
    await page.getByLabel('Password', { exact: true }).fill('correct-horse-9');
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Delete account' })
        .click();
    await expect(page).toHaveURL(/\/$/);

    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('correct-horse-9');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page.getByText(/credentials do not match/i)).toBeVisible();
});

test('owner of a shared household sees why deletion is blocked', async ({
    page,
    browser,
    baseURL,
}) => {
    const ownerEmail = uniqueEmail('owner');
    await register(page, 'Olga Owner', ownerEmail);
    await page.goto('/households');
    await page.getByLabel('New household name').fill('Blocked home');
    await page.getByRole('button', { name: 'Create household' }).click();
    await expect(
        page.getByRole('heading', { name: 'Blocked home', exact: true }),
    ).toBeVisible();

    const { execSync } = await import('node:child_process');
    const otherContext = await browser.newContext({ baseURL });
    const other = await otherContext.newPage();
    const memberEmail = uniqueEmail('member');
    await register(other, 'Mo Member', memberEmail);
    execSync(
        `php artisan tinker --execute="App\\Models\\Household::where('name','Blocked home')->latest('id')->first()->addMember(App\\Models\\User::where('email','${memberEmail}')->first(), App\\Enums\\HouseholdRole::Member);"`,
        { stdio: 'ignore' },
    );
    await otherContext.close();

    await page.goto('/settings/profile');
    await page.getByRole('button', { name: 'Delete account' }).click();
    await page.getByLabel('Password', { exact: true }).fill('correct-horse-9');
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Delete account' })
        .click();
    await expect(
        page.getByText(/Transfer ownership of your shared households/),
    ).toBeVisible();
});

test('the home page reaches sign up and has no accessibility violations', async ({
    page,
}) => {
    await page.goto('/');
    await expect(page.getByRole('heading', { level: 1 })).toContainText(
        'Seize the cart.',
    );

    await page.getByRole('button', { name: 'Roommates' }).click();
    await expect(
        page.getByRole('heading', { name: 'Shared basics, separate lists' }),
    ).toBeVisible();

    for (const scheme of ['light', 'dark'] as const) {
        await page.emulateMedia({ colorScheme: scheme });
        await page.reload();
        await page.waitForLoadState('networkidle');

        const results = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
            .analyze();

        expect(
            results.violations.map(
                (violation) =>
                    `${scheme}: ${violation.id} ${String(violation.nodes[0]?.target)}`,
            ),
        ).toEqual([]);

        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            ),
        ).toBe(true);
    }

    await page
        .getByRole('link', { name: 'Create your first list' })
        .first()
        .click();
    await expect(page).toHaveURL(/register/);
});
