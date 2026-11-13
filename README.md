# Carte Diem

Seize the cart. Make every grocery run count.

Carte Diem is a shared grocery list for households. Add items in plain language, shop together with live updates, and keep an honest record of what you bought and spent.

This is a portfolio project. It is a working app with tests, not a hosted product.

## What it does

- **Quick add.** Type `2 lb apples` and the app reads the quantity, unit, and category. Adding something already checked off brings it back instead of duplicating it.
- **Households and sharing.** Lists belong to a person or a household. Households have invitations and owner, admin, and member roles. A single list can also be shared with someone outside the household, as an editor or viewer, without exposing anything else.
- **Shopping trips.** Start a trip, check items off in store aisle order, and finish. What you bought goes into history, and anything partly bought stays on the list with the remainder.
- **Live updates.** Everyone on a list or trip sees changes as they happen, through Laravel Reverb. The app keeps working without it.
- **Purchases and spending.** Trips become purchases, and purchases can be added by hand. Spending is shown by month, store, and category, with month comparisons and price history per product.
- **Suggestions and staples.** Suggestions come from purchase history and always say why, such as "Purchased on 5 of your last 7 trips". Staples remind you on a schedule, held back during quiet hours.
- **Notifications.** An in-app inbox, per-type email preferences, and batched digests of list changes.
- **Accounts.** Email verification, optional two-factor sign-in with recovery codes, time zones, and self-service account deletion.

## How it is built

- **Backend:** Laravel 13 with Fortify for authentication and Reverb for WebSockets, on MySQL.
- **Frontend:** React 19 and TypeScript through Inertia 3, with Tailwind CSS 4 and Radix-based components. Routes are typed with Wayfinder.
- **Structure:** controllers stay thin. Business rules live in single-purpose action classes (`app/Actions`) and read-side services (`app/Services`). Authorization is in policies, and validation in form requests.

Some decisions worth a look:

- **Concurrent edits** are guarded by a `revision` on items and purchases, so a stale update is refused instead of overwriting someone else's change.
- **One active trip per list** is enforced in the database by a unique index on a generated column, not just in code.
- **Completing a trip is idempotent.** A unique source key on purchases means a double submit cannot record a shop twice.
- **Spending is computed at read time** from verified purchases, so corrections and deletions can never double count. Currencies are never added together.
- **Broadcasts carry identifiers only.** Clients refetch through authorized requests, so someone whose access was just revoked sees nothing new on an open socket.
- **Privacy by scope.** List guests never see household history, purchases, or spending, and the tests check it.

The reasoning behind these and other rules is in [docs/decisions.md](docs/decisions.md).

## Quality

- Pest feature and unit tests, run against MySQL.
- Playwright end-to-end tests at phone and desktop sizes, with axe accessibility checks on every main page and dialog, in light and dark mode.
- PHPStan at level 7, Pint, and lint and type checks for the frontend.
- A GitHub Actions workflow runs all of it.

## Running it locally

You need Docker. All PHP, Composer, Node, and Artisan commands run through [Laravel Sail](https://laravel.com/docs/sail).

First install, which needs Composer dependencies before Sail exists:

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html laravelsail/php85-composer:latest composer install --ignore-platform-reqs
```

```bash
cp .env.example .env
```

```bash
vendor/bin/sail up -d
```

```bash
vendor/bin/sail artisan key:generate
```

```bash
vendor/bin/sail artisan migrate --seed
```

```bash
vendor/bin/sail npm install
```

```bash
vendor/bin/sail npm run dev
```

The app runs at http://localhost:8000, and local mail is at http://localhost:8025.

The seed data is the demo account from the `DEMO_*` settings in `.env`: by default `demo@example.com` with the password `password`, in a "Demo Household" with four months of weekly shops, current lists, staples, and spending.

## Demo mode

The public portfolio site runs in demo mode:

```
DEMO_MODE=true
DEMO_NAME="Demo User"
DEMO_HOUSEHOLD="Demo Household"
DEMO_EMAIL=demo@example.com
DEMO_PASSWORD=password
DEMO_TIMEZONE="America/Chicago"
```

While it is on, sign-up is closed and the login page offers a one-click login as the demo user. The demo account cannot change its email or password, set up two-factor sign-in, delete itself, or send invitations, so visitors sharing it cannot lock each other out or email strangers. Everything else they change is undone by `demo:reset`, which runs daily in demo mode and can be run by hand:

```bash
vendor/bin/sail artisan demo:reset
```

Real accounts are created from the terminal, which works whether or not sign-up is open. It asks for anything not given as an option, and always asks for the password:

```bash
vendor/bin/sail artisan user:create --name="Pat Lee" --email=pat@example.com --timezone=America/Chicago
```

The tests and CI run with demo mode off, because the end-to-end tests sign up new users.

`vendor/bin/sail up -d` also starts a queue worker, the scheduler, and the Reverb server. Reverb and the scheduler read code at startup, so restart them after changing their code.

## Checks

```bash
vendor/bin/sail composer ci:check
```

The end-to-end tests need Playwright's browser installed in the container once. Rebuilding or recreating the container may require repeating the first command:

```bash
vendor/bin/sail root-shell -c "npx playwright install-deps chromium"
```

```bash
vendor/bin/sail npx playwright install chromium
```

```bash
vendor/bin/sail npm run test:e2e
```
