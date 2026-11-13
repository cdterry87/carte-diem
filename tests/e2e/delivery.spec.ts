import { expect, test } from '@playwright/test';
import {
    addItem,
    artisan,
    createHouseholdList,
    householdPair,
    mailTexts,
    register,
    runQueue,
    tinker,
    uniqueEmail,
} from './helpers';

test('a reminder email is delivered, worded as a possibility', async ({
    page,
}) => {
    const email = uniqueEmail('remind-mail');

    await register(page, 'Rae Reminder', email);
    await page.goto('/staples');
    await page.getByLabel('Staple name').fill('cold brew');
    await page.getByLabel(/Remind every/).fill('1');
    await page.getByRole('button', { name: 'Add staple' }).click();
    await expect(page.getByText('cold brew')).toBeVisible();

    tinker(
        `$id = App\\Models\\User::where('email','${email}')->value('id'); App\\Models\\StapleRule::where('owner_type','user')->where('owner_id',$id)->get()->each(fn ($r) => $r->forceFill(['created_at' => now()->subDays(2)])->save());`,
    );
    artisan('reminders:send');
    runQueue();

    await expect
        .poll(async () => (await mailTexts(page, email)).join('\n'), {
            timeout: 15000,
        })
        .toContain('You may need: cold brew.');

    const mail =
        (await mailTexts(page, email)).find((text) =>
            text.includes('cold brew'),
        ) ?? '';

    expect(mail).toContain('Items you may need soon');
    expect(mail).toContain('/staples');
});

test('digests reach others in the feed and by email, with counts and no item names', async ({
    page,
    browser,
    baseURL,
}) => {
    const { member, memberContext, household, ownerEmail, memberEmail } =
        await householdPair(page, browser, baseURL);
    const listUrl = await createHouseholdList(page, household, 'Digest list');

    await page.goto('/settings/notifications');
    await page.getByRole('checkbox', { name: 'List changes by email' }).click();
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByText('Notification settings saved.')).toBeVisible();

    await member.goto(listUrl);
    await addItem(member, 'private medicine');
    await addItem(member, 'milk');

    tinker(
        `App\\Models\\ListActivityEvent::whereNull('processed_at')->update(['created_at' => now()->subMinutes(10)]);`,
    );
    artisan('notifications:send-digests');
    runQueue();

    await page.goto('/notifications');
    await expect(page.getByText('Mia Member added 2 items.')).toBeVisible();

    await expect
        .poll(async () => (await mailTexts(page, ownerEmail)).join('\n'), {
            timeout: 15000,
        })
        .toContain('Mia Member added 2 items.');

    const mail =
        (await mailTexts(page, ownerEmail)).find((text) =>
            text.includes('added 2 items'),
        ) ?? '';

    expect(mail).not.toContain('medicine');
    expect(await mailTexts(page, memberEmail)).not.toContainEqual(
        expect.stringContaining('added 2 items'),
    );

    await page.getByRole('button', { name: /Changes to Digest list/ }).click();
    await expect(page).toHaveURL(listUrl);
    await expect(page.getByText('private medicine')).toBeVisible();

    await memberContext.close();
});
