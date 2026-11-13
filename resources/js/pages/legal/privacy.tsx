import { Link, usePage } from '@inertiajs/react';
import type { LegalFacts } from '@/components/legal/legal-page';
import { Contact, Fact, LegalPage } from '@/components/legal/legal-page';
import { edit as editProfile } from '@/routes/profile';

const linkClass = 'text-primary underline underline-offset-4';

export default function Privacy({ legal }: { legal: LegalFacts }) {
    const { name } = usePage().props;
    return (
        <LegalPage title="Privacy Policy" legal={legal}>
            <p>
                {name} is operated by{' '}
                <Fact value={legal.entity} placeholder="legal entity name" />.
                This policy explains what we collect, why, who can see it, and
                how you stay in control of it.
            </p>

            <h2>The short version</h2>
            <ul>
                <li>We do not sell your personal information.</li>
                <li>We do not show ads or use third-party trackers.</li>
                <li>You can delete your account yourself.</li>
            </ul>

            <h2>What we collect</h2>
            <ul>
                <li>
                    <strong>Account details:</strong> your name, email address,
                    time zone, language, and a hashed version of your password.
                    We cannot read your password.
                </li>
                <li>
                    <strong>What you add:</strong> shopping lists and items,
                    notes, stores, categories, favorites, staples, shopping
                    trips, the purchase records created when you complete a trip
                    or add a purchase.
                </li>
                <li>
                    <strong>Spending:</strong> merchants, dates, and amounts you
                    enter.
                </li>
                <li>
                    <strong>Households and sharing:</strong> the households you
                    belong to, your role, and the lists shared with you.
                </li>
                <li>
                    <strong>Other people&apos;s email addresses:</strong> when
                    you invite someone, we store their email address and send
                    them one invitation. Only invite people who would expect to
                    hear from you.
                </li>
                <li>
                    <strong>Technical details:</strong> your IP address and
                    browser details for signed-in sessions, a record of account
                    and sharing changes, and server logs.
                </li>
                <li>
                    <strong>Cookies:</strong> only the cookies needed to keep
                    you signed in and remember your display settings. No
                    advertising or analytics cookies.
                </li>
            </ul>

            <h2>How we use it</h2>
            <ul>
                <li>
                    To run {name} and show your data to you and the people you
                    share with.
                </li>
                <li>
                    To send account, verification, password reset, and
                    invitation emails, plus the reminders and notifications you
                    leave turned on.
                </li>
                <li>
                    To suggest items you may need. Suggestions are calculated
                    from the purchase history of that personal or household
                    space, using simple rules. We do not use them for
                    advertising, and guests never see suggestions drawn from a
                    household&apos;s history.
                </li>
                <li>
                    To update open pages live. While you use the app, your
                    browser keeps a connection to our server for this. The
                    updates tell your browser something changed, and contain no
                    list contents.
                </li>
                <li>
                    To keep the service secure, prevent abuse, and fix problems.
                </li>
                <li>To meet legal obligations.</li>
            </ul>
            <p>
                We do not use your data for advertising or to build profiles for
                others.
            </p>

            <h2>Who can see your data</h2>
            <ul>
                <li>
                    <strong>Personal lists</strong> are private to you unless
                    you share them.
                </li>
                <li>
                    <strong>Household members</strong> can see that
                    household&apos;s lists, trips, purchase history, and
                    spending, along with other members&apos; names and email
                    addresses.
                </li>
                <li>
                    <strong>Guests</strong> you share a list with can see only
                    that list and its trips. They cannot see household purchase
                    records or spending.
                </li>
                <li>
                    <strong>Service providers</strong> that host {name} and
                    deliver its email handle data only on our instructions.
                </li>
                <li>
                    <strong>Authorities or others</strong> where the law
                    requires it, or to protect people&apos;s safety and our
                    legal rights. If {name} is ever transferred to a new owner,
                    this policy continues to apply to your data until you are
                    told otherwise.
                </li>
            </ul>

            <h2>Your choices and rights</h2>
            <ul>
                <li>
                    <strong>Correct:</strong> change your details in{' '}
                    <Link href={editProfile()} className={linkClass}>
                        profile settings
                    </Link>
                    .
                </li>
                <li>
                    <strong>Delete:</strong> delete your account from profile
                    settings at any time. See below for what that removes.
                </li>
                <li>
                    <strong>Ask us:</strong> contact us to use any other privacy
                    right you have, including if you received an invitation and
                    have no account. We will not treat you differently for doing
                    so.
                </li>
            </ul>

            <h2>When you delete your account</h2>
            <p>
                If you own a household that has other members, you must transfer
                ownership first, so the household keeps an owner.
            </p>
            <p>Deleting your account permanently removes:</p>
            <ul>
                <li>
                    your account details, sign-ins, favorites, notifications,
                    and notification and suggestion settings;
                </li>
                <li>
                    your personal lists, trips, purchases, staples, stores,
                    categories, and products;
                </li>
                <li>
                    households you own with no other members, and their data;
                </li>
                <li>invitations sent to your email address.</li>
            </ul>
            <p>
                Records in households you belong to stay with the household,
                because they are shared records of what that household bought.
                Your name is removed from them. Our internal record of account
                and sharing changes also stays, with your name and email
                removed. It may keep a random identifier that no longer links to
                you.
            </p>

            <h2>How long we keep data</h2>
            <ul>
                <li>Your account data stays until you delete your account.</li>
                <li>
                    Deleted lists stay available so they can be restored.
                    Deleted lists and households also stay so purchase history
                    stays intact. They are permanently removed when their
                    owner&apos;s account is deleted.
                </li>
                <li>
                    Household records stay until the household&apos;s owner
                    deletes their account while no other members remain.
                </li>
                <li>
                    Server logs are kept for{' '}
                    <Fact
                        value={legal.log_retention}
                        placeholder="log retention period"
                    />
                    .
                </li>
                <li>
                    Backups are kept for{' '}
                    <Fact
                        value={legal.backup_retention}
                        placeholder="backup retention period"
                    />
                    , so deleted data can remain in backups until they expire.
                </li>
            </ul>

            <h2>Security</h2>
            <p>
                We hash passwords, store invitation links in hashed form, check
                permissions on every request, and limit repeated attempts. No
                service can promise perfect security, so please use a strong,
                unique password.
            </p>

            <h2>Children</h2>
            <p>
                {name} is not meant for children under 13, and we do not
                knowingly collect their data. Contact us if you think a child
                has signed up, and we will delete the account.
            </p>

            <h2>California and other US states</h2>
            <p>
                You may have the right to know, access, correct, and delete your
                personal information, and to opt out of its sale or sharing. We
                do not sell it or share it for targeted advertising.
            </p>

            <h2>EEA and UK</h2>
            <p>
                We process your data to provide the service you asked for, to
                pursue our legitimate interests in keeping it secure, and to
                meet legal obligations. You may have the right to access,
                correct, delete, restrict, or object to processing, to move your
                data, and to complain to your local data protection authority.
            </p>

            <h2>Changes to this policy</h2>
            <p>
                We will update the effective date when this policy changes, and
                tell you about significant changes before they apply.
            </p>

            <h2>Contact</h2>
            <p>
                Questions or requests: <Contact legal={legal} />.
            </p>
        </LegalPage>
    );
}
