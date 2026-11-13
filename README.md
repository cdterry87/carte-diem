# Carte Diem

Seize the cart. Make every grocery run count.

Carte Diem is a shared grocery list for households. Add items in plain language, shop together with live updates, and keep an honest record of what you bought and spent.

This is a portfolio project. It is a working app with tests, not a hosted product.

## What it does

- **Quick add.** Type `2 lb apples` and the app reads the quantity, unit, and category. Adding something already checked off brings it back instead of duplicating it.
- **Households and sharing.** Lists belong to a person or a household. Households have invitations and owner, admin, and member roles. A single list can also be shared with someone outside the household, as an editor or viewer, without exposing anything else.
- **Shopping trips.** Start a trip, check items off in store aisle order, and finish. What you bought goes into history, and anything partly bought stays on the list with the remainder.
- **Live updates.** Everyone on a list or trip sees changes as they happen, through Laravel Reverb. The app keeps working without it, and demo mode turns it off.
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

While it is on, sign-up is closed and the login page offers a one-click login as the demo user. Live updates are off too, whatever `BROADCAST_CONNECTION` says, so the site needs no Reverb server: pages refresh on request and the trip page polls. The demo account cannot change its name, email, or password, set up two-factor sign-in, delete itself, send invitations, or rename, delete, leave, or hand over its household, so visitors sharing it cannot lock each other out, email strangers, or wreck what the next visitor sees. Everything else they change is undone by `demo:reset`, which runs daily in demo mode and can be run by hand:

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

## Deploying

These steps are for a single Linux server running Nginx and PHP-FPM, such as one set up by Laravel Forge or by hand. The app needs:

- PHP 8.3 or newer with the extensions Laravel needs, plus `pdo_mysql`
- Composer
- Node 24, for building the frontend
- MySQL 8.0 or newer, because the schema uses generated columns
- HTTPS
- An SMTP service for verification, reset, and notification emails

Demo mode needs no Redis and no WebSocket server.

### 1. Install

```bash
git clone https://github.com/cdterry87/carte-diem.git && cd carte-diem
```

```bash
composer install --no-dev --optimize-autoloader
```

```bash
cp .env.example .env && php artisan key:generate
```

### 2. Configure `.env`

Change at least these from the local defaults:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
LOG_LEVEL=warning

DB_HOST=127.0.0.1
DB_DATABASE=carte_diem
DB_USERNAME=...
DB_PASSWORD=...

SESSION_SECURE_COOKIE=true

MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=hello@your-domain.example

LEGAL_ENTITY_NAME=...
LEGAL_CONTACT_EMAIL=...
LEGAL_EFFECTIVE_DATE=...
```

The `LEGAL_*` values fill in the privacy policy, and unset values show as placeholders.

For the portfolio site, keep `DEMO_MODE=true` with the `DEMO_*` values from [Demo mode](#demo-mode). Live updates are then off, and `BROADCAST_CONNECTION` and the `REVERB_*` values are ignored.

`APP_ENV=production` matters beyond error pages:

- Passwords set through sign-up, reset, and `user:create` must be at least 12 characters, with mixed case, numbers, and symbols, and must not appear in known breaches.
- HSTS is sent.
- Destructive database commands such as `migrate:fresh` are refused.
- Inertia's development tools are turned off.

### 3. Build and migrate

The build runs Artisan to generate the typed routes, so Composer dependencies and `.env` must be in place first:

```bash
npm ci && npm run build
```

```bash
php artisan migrate --force
```

Create the demo account, or real accounts, or both:

```bash
php artisan demo:reset
```

```bash
php artisan user:create
```

Cache the configuration, routes, and views:

```bash
php artisan optimize
```

### 4. Web server

Point the site's document root at `public/` and serve it over HTTPS, with the standard Laravel Nginx configuration from the [deployment docs](https://laravel.com/docs/deployment#nginx).

If TLS ends at a load balancer or CDN in front of the server, rather than at Nginx, add `$middleware->trustProxies(at: '*')` (or the proxy's addresses) in `bootstrap/app.php`. Otherwise the app cannot tell requests are secure, and it builds `http` links and skips HSTS.

### 5. Background processes

Add the scheduler to cron. It runs staple reminders, activity digests, and the daily `demo:reset`:

```
* * * * * cd /path/to/carte-diem && php artisan schedule:run >> /dev/null 2>&1
```

Keep a queue worker running with Supervisor, for email and notifications:

```ini
[program:carte-diem-queue]
command=php /path/to/carte-diem/artisan queue:work --tries=3 --timeout=120 --max-time=3600
user=forge
autostart=true
autorestart=true
stopwaitsecs=130
stdout_logfile=/path/to/carte-diem/storage/logs/queue.log
```

### 6. Live updates outside demo mode

To run with live updates, set `DEMO_MODE=false`, `BROADCAST_CONNECTION=reverb`, and new random `REVERB_APP_ID`, `REVERB_APP_KEY`, and `REVERB_APP_SECRET` values. Then:

- Set `REVERB_HOST` to the public host, `REVERB_PORT=443`, and `REVERB_SCHEME=https`. The `VITE_REVERB_*` values copy these, and are built into the frontend, so set them before `npm run build`.
- Keep `php artisan reverb:start --host=127.0.0.1 --port=8080` running with Supervisor, like the queue worker.
- Proxy WebSocket requests from Nginx to it, as in the [Reverb docs](https://laravel.com/docs/reverb#web-server).

Reverb is free and runs on the same server.

### Updating

```bash
git pull
```

```bash
composer install --no-dev --optimize-autoloader
```

```bash
npm ci && npm run build
```

```bash
php artisan migrate --force && php artisan optimize
```

```bash
php artisan queue:restart
```

If Reverb is running, also run `php artisan reverb:restart`. Workers and Reverb load code once at startup, so they need the restart to pick up changes.
