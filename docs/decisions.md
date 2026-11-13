# Design decisions

The rules Carte Diem follows, and why. Earlier versions of this file also covered features that were later removed. The full record is on the `archive/full-app` branch.

## Lists and items

- **Quick add** parses input such as `2 lb apples` into editable fields. Unknown names create a product in the list's space. Category comes from the product's default, then a keyword map, then Other.
- **Adding a checked-off item again** marks the existing item needed instead of creating a duplicate. A second copy is allowed while the first is still needed.
- **Direct check-off:** checking an item outside a trip sets it to `satisfied` and moves it to the bottom. It never writes a purchase record.
- **Favorites are per user,** stored in pivot tables for lists and products.
- **Deletion is soft,** with restore. Only the list creator, an admin, or the owner can delete or share a household list. Any member can edit and archive it, and manage stores and categories.
- **Edits use revisions.** Each item and purchase carries a `revision`. A stale update is refused rather than overwriting someone else's change.

## Households and sharing

- **Ownership** uses `owner_type` and `owner_id` with an enforced morph map. A list, product, store, or purchase belongs to either a person or a household.
- **Household roles** are owner, admin, and member. Only the owner can delete a household, and only after typing its name. The owner must transfer ownership before leaving.
- **List guests** are people outside the household given one list, as editors or viewers. They see only that list and its trips. Suggestions for guests use only names already on the shared list plus their own products, never household history.
- **Invitations** store a hash of the token, never the token. A newer invitation to the same email replaces a pending one. Accepting works only for the invited email.
- **Account deletion** is blocked while the user owns a household with other members. Otherwise personal data is deleted and `created_by` on household records is anonymized, so household history remains.

## Shopping trips

- **One active trip per list,** enforced by a unique index on a generated column. Starting another sends people to the existing trip.
- **Trips snapshot** the needed items when they start. Later list edits never change a trip, so history shows what really happened.
- **After completion,** purchased items become `satisfied` and can be marked needed again with one tap. Completing twice returns the first result.
- **Partial purchases:** when the quantity bought is less than requested, the item stays `needed` with the remainder. If the units differ, the item keeps its original quantity and is flagged for review.
- **Edits during a trip:** a purchased item stays needed if its requested quantity rose above what was bought. Otherwise the latest list edit wins.
- **Store aisle order** applies through a list's default store on the list page, and through the trip's store on the trip page.

## Purchases and spending

- **Trip purchases** use `entry_method=trip` and `verification_status=verified`. Their totals are optional, so spending queries tolerate null totals.
- **Manual purchases** need a merchant, date, and total. Dates are stored at noon in the entering user's zone. The merchant links to a store when the name matches one in that space. Manual lines become products, so they feed suggestions the same way trips do.
- **Trip amounts** are optional. Members can enter a total and item prices during a trip, which carry onto the purchase. A cancelled trip's amounts never reach spending. Guests running a trip cannot see or set amounts.
- **Totals are never derived.** Subtotal, tax, discount, and line prices are optional, and the total does not have to equal their sum. The form points out a difference without blocking.
- **Needs checking** purchases stay out of spending and suggestions until confirmed.
- **Corrections** replace stored values and bump `revision`. Spending is summed at read time, so a correction cannot count twice. The audit log keeps previous amounts.
- **Duplicate protection:** a unique `source_kind` and `source_identifier` stops double writes. A likely duplicate (same space, day, merchant, and total) asks for confirmation. An equal total alone never counts.
- **Moving a purchase** to another space takes its creator or a manager. Stores, products, and custom categories are matched again in the new space. A trip purchase cannot move.
- **Spending** covers verified purchases with a total, grouped by month in the owner's time zone so all members see the same figures. Currencies are never added together.
- **Spending by category** uses priced lines and states how much of the total they cover.
- **Month comparisons** use the last complete month against the month before and the same month a year earlier. A partial current month would always look like a drop.
- **Price history** covers the last 24 months and compares only within one currency and one unit family, such as g with kg. `oz` only matches itself, because it can mean weight or volume.

## Suggestions and reminders

- **Suggestions** are calculated on request from verified purchases in the last 365 days. An interval needs at least 3 purchases, and items bought together need at least 3 shared trips. Every suggestion says why it is there.
- **Dismissals are per user.** Dismiss hides for 30 days, snooze for 7, and mute until lifted.
- **Staple reminders** run hourly, batched into one notice per person per space, and at most once per staple per window. Quiet hours hold reminders back rather than dropping them.
- **List activity digests** run every 5 minutes, wait for edits to settle, and contain counts, not item names, because the text can go by email.
- **Notification defaults:** everything is on in app. Only reminders are on by email. Invitation emails always send.

## Live updates

- **Reverb** broadcasts identifiers only. Clients refetch through authorized requests, so someone whose access was just revoked sees nothing new on an open socket.
- **The app runs without Reverb.** Pages fall back to refreshing on request, and the trip page polls.

## Accounts and privacy

- **Time zones:** registration saves the browser's zone, and users can change it. Dates display in the viewer's zone. Households have their own zone for shared figures.
- **Two-factor sign-in** is optional, built on Fortify with code confirmation and recovery codes. Changing it needs a recent password.
- **Unverified accounts** can still delete themselves.
- **The privacy policy** is a draft written to match the app's behavior, not legal advice. `LEGAL_*` settings fill in the entity, contact, and retention details, and unset values show as placeholders.

## Demo mode

- **For the portfolio site.** `DEMO_MODE` closes sign-up and adds a one-click demo login. The demo credentials stay on the server and never reach the page.
- **One shared account,** marked `is_demo`. It cannot change its email or password, set up two-factor sign-in, delete itself, or send invitations, and password reset links are never sent to it. Everything else is undone by `demo:reset`, which deletes the demo account and builds it again daily, ending any open demo sessions.
- **Fixed sample data,** not random, so every reset looks the same: one household, three stores, sixteen weekly shops with rising prices on a few staples, and lists in progress.
- **`demo:reset` never takes over a real account.** It refuses when a regular account already uses the demo email, and `user:create` keeps that email free.
- **`user:create`** adds verified accounts from the terminal. It always asks for the password, so it never lands in shell history.

## Technical choices

- Laravel React starter kit (Fortify, Inertia 3, Wayfinder), with Pest for tests and Playwright with axe for end-to-end and accessibility checks.
- Laravel Sail for local development. Tests run on MySQL, because locking and generated columns matter here.
- `APP_NAME`, `APP_TAGLINE`, and `APP_TAGLINE_LONG` are settings, read at runtime.
