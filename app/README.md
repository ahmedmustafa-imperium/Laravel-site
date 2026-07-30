# SNH Packing Commerce

A Laravel e-commerce storefront inspired by [snhuae.com](https://snhuae.com/), with a responsive product catalog, guest and customer checkout, inventory-aware ordering, coupons, customer accounts, and an administration area.

## Included features

- Responsive home, collection, product, cart, checkout, about, and contact pages
- Predictive search, product filters/sorting, variants, wishlist, and recently viewed products
- Customer registration, login, password reset, profile updates, and order history
- Guest or authenticated checkout with cash on delivery, Stripe Checkout, and PayPal Orders
- `WELCOME10`-style percentage/fixed coupons, usage limits, start/end dates, and minimum totals
- Transactional stock reservation/restoration with a complete inventory movement audit
- Checkout idempotency and expiring payment reservations to prevent duplicate orders or permanent stock holds
- Queued order-confirmation emails
- Admin dashboards for products, categories, inventory history, orders, and coupons
- Contact-message and newsletter-subscriber storage
- Signed order confirmation links, verified payment callbacks/webhooks, CSRF protection, throttling, and admin authorization

## Requirements

- PHP 8.3 or later with PDO MySQL, OpenSSL, Mbstring, Fileinfo, and Sodium
- Composer 2
- MySQL 8 or compatible MariaDB
- Node.js 20 or later and npm

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create a MySQL database and update these `.env` values:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=snh_store
DB_USERNAME=snh_user
DB_PASSWORD=your-strong-password
```

Then initialize and build the application:

```bash
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
php artisan serve
```

The seeder always respects `SEED_DEMO_CATALOG=false` and `SEED_DEMO_USERS=false` by default, so running it in production will not overwrite live catalog, stock, or coupon data.

For a local demonstration only, set the following before running `php artisan db:seed`:

```dotenv
SEED_DEMO_CATALOG=true
SEED_DEMO_USERS=true
PAYMENTS_DEMO=true
```

Local demo accounts are:

- Administrator: `admin@snh.local` / `password`
- Customer: `customer@example.com` / `password`

Demo users and the demo catalog are refused outside `local` or `testing`. To create a production administrator through the seeder, set `SEED_ADMIN_EMAIL` and a `SEED_ADMIN_PASSWORD` of at least 12 characters, run the seeder once, then remove both values.

## Background processes

Order emails use Laravel’s database queue. Run a worker in development:

```bash
php artisan queue:work --tries=3
```

Online-payment orders reserve stock while the provider window is active. The scheduler releases expired reservations and restores coupon usage:

```bash
php artisan schedule:work
```

In production, configure one cron entry:

```cron
* * * * * cd /path/to/snh-commerce && php artisan schedule:run >> /dev/null 2>&1
```

Also supervise `php artisan queue:work --sleep=3 --tries=3 --max-time=3600` with systemd, Supervisor, or the hosting platform’s worker facility.

## Payments

Production payment mode is the safe default (`PAYMENTS_DEMO=false`). Add credentials only on the server:

```dotenv
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=

PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=
PAYPAL_SECRET=
PAYPAL_WEBHOOK_ID=
```

Register these HTTPS webhook endpoints with the providers:

- Stripe: `https://your-domain.example/payments/webhooks/stripe`
- PayPal: `https://your-domain.example/payments/webhooks/paypal`

Stripe signatures are checked locally with a five-minute tolerance. PayPal events are verified through PayPal’s verification API. Both integrations also match the provider reference, order number, amount, and currency before marking an order paid.

Stripe sessions and local stock reservations expire together after 35 minutes. PayPal reservations use six hours because PayPal’s Orders API keeps the payer-approval window available for that period. The scheduled cleanup must be running in production.

The admin “Refunded” state is deliberately a record of a refund already completed through Stripe, PayPal, or the original payment channel; the form requires explicit confirmation and does not claim to initiate a provider refund.

## Email and production safety

Configure a real SMTP or supported mail transport and update `MAIL_FROM_ADDRESS`. Before launch, use:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
PAYMENTS_DEMO=false
SESSION_SECURE_COOKIE=true
```

Terminate TLS at the web server or load balancer, keep `.env` out of source control, restrict the admin account, and cache configuration after deployment:

```bash
php artisan optimize
```

## Development and verification

```bash
npm run dev
php artisan test
vendor/bin/pint --test
php artisan route:list --except-vendor
```

The automated feature suite covers storefront rendering/search, public forms, registration/login, admin authorization, audited inventory edits, coupons and shipping totals, COD checkout, order emails, stock reservation/restoration, checkout idempotency, payment callback ownership, and expired-order cleanup.

## Main directories

- `app/Http/Controllers` — storefront, checkout, account, and admin controllers
- `app/Services` — cart, orders, inventory, Stripe, and PayPal integrations
- `resources/views` — Blade storefront, account, checkout, email, and admin views
- `resources/css` and `resources/js` — responsive design and storefront interactions
- `database/migrations` and `database/seeders` — MySQL schema and opt-in sample catalog
- `tests/Feature` — end-to-end commerce behavior
