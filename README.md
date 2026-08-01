# Oyalo NetPass

A Laravel application for selling Paystack-backed MikroTik hotspot vouchers, managing packages and devices, and synchronizing access commands with RouterOS.

## Voucher behavior

Every completed sale creates a new, independent voucher. A purchaser's phone number is used for delivery and payment context only; it is **not** an account identifier. Therefore, two successful purchases made with the same phone number produce two customer/voucher records with different codes and separate expiry times.

A voucher is used as both the MikroTik username and password.

## Local setup

Requirements:

- PHP 8.3+
- Composer
- Node.js/npm
- SQLite or another Laravel-supported database

```bash
composer run setup
composer run dev
```

Copy `.env.example` to `.env` and configure at least:

```dotenv
APP_URL=https://your-public-host.example
PAYSTACK_SECRET_KEY=sk_live_...
ARKESEL_SMS_API_KEY=...
ARKESEL_SMS_SENDER_ID=OyaloWiFi
```

A location also needs a valid Paystack subaccount before its online checkout is enabled.

Run the scheduler every minute in production. It expires vouchers, queues MikroTik removal commands, and marks routers offline when their heartbeat becomes stale:

```cron
* * * * * cd /path/to/netpassv3 && php artisan schedule:run >> /dev/null 2>&1
```

A queue worker is required for voucher SMS messages, expiry SMS messages, and owner emails. Keep it alive with Supervisor or systemd:

```bash
php artisan queue:work --sleep=1 --tries=3 --timeout=60
```

Run tests with:

```bash
composer test
```

## Payment processing

Checkout context (phone, optional MAC/device, package, amount, and location) is stored on the pending payment before redirecting to Paystack. The callback:

1. Looks up the reference within the URL's location.
2. verifies the transaction directly with Paystack;
3. checks reference, amount, and currency against the stored payment;
4. locks the payment and fulfills it transactionally;
5. creates exactly one new voucher and links it to the payment; and
6. queues the voucher on every router belonging to the location.

Callbacks are idempotent: replaying a completed reference does not create another voucher.

Configure Paystack's dashboard webhook URL as:

```text
https://your-public-host.example/api/paystack/webhook
```

The webhook verifies Paystack's SHA-512 signature and uses the same idempotent fulfillment path as the browser callback. This ensures a paid voucher is still issued if the customer closes the browser before returning from Paystack.

## Router API

All router requests require these headers:

```http
X-Router-ID: RTR-000001
X-Router-Token: your-router-token
```

Endpoints:

| Method | Endpoint | Purpose |
|---|---|---|
| `POST` | `/api/router/heartbeat` | Mark the router online |
| `GET` | `/api/router/commands` | Fetch up to 100 pending commands as JSON |
| `POST` | `/api/router/commands/{id}/ack` | Acknowledge with `completed` or `failed` |
| `GET` | `/api/router/data` | Fetch router details and pending commands |

Example command response:

```json
{
  "status": "success",
  "router_id": "RTR-000001",
  "commands": [
    {
      "id": 42,
      "type": "CREATE_USER",
      "script": ":local UserIds [/ip hotspot user find where name=\"OY-ABC123DEF4\"]; :if ([:len $UserIds] = 0) do={ /ip hotspot user add name=\"OY-ABC123DEF4\" password=\"OY-ABC123DEF4\" profile=\"oyalo-1hour-8\" limit-uptime=60m comment=\"Managed by Oyalo\"; } else={ /ip hotspot user set $UserIds password=\"OY-ABC123DEF4\" profile=\"oyalo-1hour-8\" disabled=no limit-uptime=60m comment=\"Managed by Oyalo\"; /ip hotspot user reset-counters $UserIds; }",
      "payload": {
        "username": "OY-ABC123DEF4",
        "password": "OY-ABC123DEF4",
        "profile": "oyalo-1hour-8",
        "duration_minutes": 60
      },
      "created_at": "2026-07-30T10:00:00Z"
    }
  ]
}
```

`mikrotik_sync.rsc` is a RouterOS 7.13+ polling script. Each command returned by `/api/router/commands` includes an executable RouterOS `script` string alongside its type and payload, allowing the router to simply parse (`:parse`) and execute the script directly. Replace its URL, router ID, and token, import it, and schedule it at the interval appropriate for the hotspot. Keep TLS certificate checking enabled and install the required CA certificate on the router.

## Deployment

After pulling an update:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
npm ci
npm run build
```

Keep the queue worker running, and monitor `storage/logs/laravel.log`, `php artisan queue:failed`, failed SMS/email entries, pending router commands, stale routers, and pending payments.
