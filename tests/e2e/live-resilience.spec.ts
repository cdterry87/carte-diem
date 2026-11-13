import type { Page } from '@playwright/test';
import { expect, test } from '@playwright/test';
import {
    addItem,
    createHouseholdList,
    householdPair,
    runQueue,
    tinker,
    uniqueEmail,
} from './helpers';

test.skip(!!process.env.E2E_NO_LIVE, 'Live updates are not running here');

async function waitForSocket(page: Page) {
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);
}

test('the bell updates live when a notification arrives', async ({
    page,
    browser,
    baseURL,
}) => {
    const { memberContext, household, ownerEmail } = await householdPair(
        page,
        browser,
        baseURL,
    );

    await page.goto('/dashboard');
    await expect(
        page.getByRole('link', { name: 'Notifications', exact: true }),
    ).toBeVisible();
    await waitForSocket(page);

    tinker(
        `App\\Models\\User::where('email','${ownerEmail}')->first()->notify(new App\\Notifications\\TripCompletedNotification('${household}', 'Done.', '/lists'));`,
    );
    runQueue();

    await expect(
        page.getByRole('link', { name: /Notifications, 1 unread/ }),
    ).toBeVisible({ timeout: 10000 });

    await memberContext.close();
});

test('the household page updates live when someone joins', async ({
    page,
    browser,
    baseURL,
}) => {
    const { memberContext, household } = await householdPair(
        page,
        browser,
        baseURL,
    );
    const email = uniqueEmail('joiner');

    await page.goto('/households');
    await page.getByRole('link', { name: new RegExp(household) }).click();
    await waitForSocket(page);

    const joinerContext = await browser.newContext({ baseURL });
    const joiner = await joinerContext.newPage();
    const { register } = await import('./helpers');

    await register(joiner, 'Jo Joiner', email);
    tinker(
        `$h = App\\Models\\Household::where('name','${household}')->first(); $i = $h->invitations()->create(['email' => '${email}', 'role' => 'member', 'token_hash' => hash('sha256', '${email}'), 'expires_at' => now()->addDay()]); app(App\\Actions\\Households\\RespondToHouseholdInvitation::class)->accept($i, App\\Models\\User::where('email','${email}')->first());`,
    );
    runQueue();

    await expect(page.getByText('Jo Joiner')).toBeVisible({ timeout: 10000 });

    await joinerContext.close();
    await memberContext.close();
});

test('a dropped connection refetches once it reconnects', async ({
    page,
    browser,
    baseURL,
}) => {
    const { member, memberContext, household } = await householdPair(
        page,
        browser,
        baseURL,
    );
    const listUrl = await createHouseholdList(
        page,
        household,
        'Reconnect list',
    );
    let blocked = false;
    const sockets: { close: () => Promise<void> }[] = [];

    await member.routeWebSocket(/:8080\//, (socket) => {
        if (blocked) {
            void socket.close();

            return;
        }

        socket.connectToServer();
        sockets.push(socket);
    });

    await member.goto(listUrl);
    await waitForSocket(member);
    expect(sockets.length).toBeGreaterThan(0);

    blocked = true;
    await Promise.all(sockets.map((socket) => socket.close()));
    await member.waitForTimeout(500);

    await addItem(page, 'missed while offline');
    runQueue();
    await member.waitForTimeout(1500);
    await expect(member.getByText('missed while offline')).toHaveCount(0);

    blocked = false;

    await expect(member.getByText('missed while offline')).toBeVisible({
        timeout: 45000,
    });

    await memberContext.close();
});

test('the trip page falls back to polling when live updates are unavailable', async ({
    page,
    browser,
    baseURL,
}) => {
    test.setTimeout(90000);

    const { member, memberContext, household } = await householdPair(
        page,
        browser,
        baseURL,
    );
    const listUrl = await createHouseholdList(page, household, 'Polling list');

    await addItem(page, 'polled milk');
    await page.getByRole('button', { name: 'Start trip' }).click();
    await expect(page).toHaveURL(/trips\//);
    const tripUrl = page.url();

    await member.routeWebSocket(/:8080\//, (socket) => {
        void socket.close();
    });
    await member.goto(tripUrl);
    await expect(member.getByText('0 of 1 in the cart')).toBeVisible();

    await page.getByRole('checkbox', { name: /polled milk/ }).click();

    await expect(member.getByText('1 of 1 in the cart')).toBeVisible({
        timeout: 25000,
    });
    expect(listUrl).toBeTruthy();

    await memberContext.close();
});

test('the app keeps working with live updates down', async ({
    page,
    browser,
    baseURL,
}) => {
    const { member, memberContext, household } = await householdPair(
        page,
        browser,
        baseURL,
    );
    const listUrl = await createHouseholdList(page, household, 'Offline list');

    await member.routeWebSocket(/:8080\//, (socket) => {
        void socket.close();
    });
    await member.goto(listUrl);
    await addItem(member, 'still works');
    await expect(member.getByText('still works')).toBeVisible();

    await memberContext.close();
});
