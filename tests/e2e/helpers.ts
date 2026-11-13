import { execSync } from 'node:child_process';
import type { Browser, BrowserContext, Page } from '@playwright/test';
import { expect } from '@playwright/test';

const mailpit = process.env.E2E_MAILPIT_URL ?? 'http://mailpit:8025';

export function uniqueEmail(prefix: string): string {
    return `${prefix}-${Date.now()}-${Math.floor(Math.random() * 1000)}@cartediem.test`;
}

export function runQueue(): void {
    execSync('php artisan queue:work --stop-when-empty', { stdio: 'ignore' });
}

/** Waits for mail to this address containing a link that matches the pattern. */
export async function mailLink(
    page: Page,
    email: string,
    pattern: RegExp,
): Promise<string> {
    let link: string | undefined;

    await expect
        .poll(
            async () => {
                const search = await page.request.get(
                    `${mailpit}/api/v1/search?query=${encodeURIComponent(`to:"${email}"`)}`,
                );
                const { messages } = await search.json();

                for (const { ID } of messages ?? []) {
                    const message = await page.request.get(
                        `${mailpit}/api/v1/message/${ID}`,
                    );
                    const { Text } = await message.json();

                    link = (Text as string).match(pattern)?.[0] ?? link;
                }

                return link;
            },
            { timeout: 15000 },
        )
        .toBeTruthy();

    const url = new URL(link as string);

    return url.pathname + url.search;
}

export async function register(
    page: Page,
    name: string,
    email: string,
): Promise<void> {
    await page.goto('/register');
    await page.getByLabel('Name').fill(name);
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('correct-horse-9');
    await page.getByLabel('Confirm password').fill('correct-horse-9');
    await page.getByRole('button', { name: /create account/i }).click();
    await expect(page).toHaveURL(/verify-email|email\/verify/);

    await page.goto(await mailLink(page, email, /https?:\/\/\S*verify\S+/));
    await expect(page).toHaveURL(/dashboard/);
}

/** Plain text of every message delivered to this address. */
export async function mailTexts(page: Page, email: string): Promise<string[]> {
    const search = await page.request.get(
        `${mailpit}/api/v1/search?query=${encodeURIComponent(`to:"${email}"`)}`,
    );
    const { messages } = await search.json();
    const texts: string[] = [];

    for (const { ID, Subject } of messages ?? []) {
        const message = await page.request.get(
            `${mailpit}/api/v1/message/${ID}`,
        );
        const { Text } = await message.json();

        texts.push(`${Subject}\n${Text}`);
    }

    return texts;
}

/** Runs PHP in the app, for setup that has no screen, such as making time pass. */
export function tinker(code: string): void {
    execSync('php artisan tinker', {
        input: code,
        stdio: ['pipe', 'ignore', 'ignore'],
    });
}

export function artisan(command: string): void {
    execSync(`php artisan ${command}`, { stdio: 'ignore' });
}

export async function createList(page: Page, title: string): Promise<string> {
    await page.goto('/lists');
    await page.getByLabel('New list name').fill(title);
    await page.getByRole('button', { name: 'Create list' }).click();
    await expect(page.getByRole('heading', { name: title })).toBeVisible();

    return page.url();
}

export async function addItem(page: Page, entry: string): Promise<void> {
    const quickAdd = page.getByRole('combobox', { name: 'Add an item' });

    await quickAdd.fill(entry);
    await quickAdd.press('Enter');
    await expect(quickAdd).toHaveValue('');
}

/** Creates a household with one owner page and a second member in their own browser context. */
export async function householdPair(
    page: Page,
    browser: Browser,
    baseURL: string | undefined,
): Promise<{
    member: Page;
    memberContext: BrowserContext;
    household: string;
    ownerEmail: string;
    memberEmail: string;
}> {
    const ownerEmail = uniqueEmail('pair-owner');
    const memberEmail = uniqueEmail('pair-member');
    const household = `Home ${Date.now()}`;

    await register(page, 'Olive Owner', ownerEmail);
    await page.goto('/households');
    await page.getByLabel('New household name').fill(household);
    await page.getByRole('button', { name: 'Create household' }).click();
    await expect(
        page.getByRole('heading', { name: household, exact: true }),
    ).toBeVisible();

    const memberContext = await browser.newContext({ baseURL });
    const member = await memberContext.newPage();

    await register(member, 'Mia Member', memberEmail);
    tinker(
        `App\\Models\\Household::where('name','${household}')->first()->addMember(App\\Models\\User::where('email','${memberEmail}')->first(), App\\Enums\\HouseholdRole::Member);`,
    );

    return { member, memberContext, household, ownerEmail, memberEmail };
}

export async function createHouseholdList(
    page: Page,
    household: string,
    title: string,
): Promise<string> {
    await page.goto('/lists');
    await page.getByLabel('New list name').fill(title);
    await page.getByRole('combobox', { name: 'List owner' }).click();
    await page.getByRole('option', { name: household }).click();
    await page.getByRole('button', { name: 'Create list' }).click();
    await expect(page.getByRole('heading', { name: title })).toBeVisible();

    return page.url();
}
