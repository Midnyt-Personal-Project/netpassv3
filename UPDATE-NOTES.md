# Update Notes — SMS reliability fix (2026-08-13)

This is a **critical SMS fix**. Apply it on top of the previous update.
All SMS in the system now go out reliably, with no dependency on a queue worker.

## Why SMS was not arriving

1. **Queue worker dependency (the main cause).** Voucher SMS (manual buying),
   expiry SMS, and announcement SMS were all dispatched as queue jobs to the
   `jobs` database table. If `queue:work` was not running (or crashed/restarted),
   those SMS simply never left the system. The Arkesel dashboard always worked
   because it sends directly.
2. **Response checking bug.** Arkesel V1 always answers HTTP 200 — even for
   failures. The old code only checked the HTTP status, so rejected messages
   (e.g. `105 Insufficient balance`, `102 Authentication failed`) were logged
   as "sent" and nobody could see what went wrong.
3. **Number format.** Only one format was tried per number. If the gateway
   rejected it, there was no second attempt.

## What changed

### 1. Voucher SMS (manual buy + online buy) — sent immediately
- `AdminController::createSubscription` (manual WiFi buying) now sends the
  voucher SMS directly during the request — no queue involved.
- `PaymentFulfillmentService::fulfill` (Paystack webhook / online purchase)
  also sends the voucher SMS directly.
- The old `SendSubscriptionCredentialsSms` queue job is no longer dispatched
  (the file is kept so any old queued rows still resolve).

### 2. Expiry SMS — sent by the existing every-minute cron
- `ExpireSubscriptions` now sends the expiry SMS directly while expiring
  subscriptions (it already runs every minute via the scheduler).

### 3. Announcement SMS — sent by the every-minute cron
- New service `AnnouncementSmsSender` sends blasts directly, in batches
  (default 100 SMS per scheduler minute), and tracks progress per customer
  through the SMS log. A blast resumes safely after a crash/server restart
  and never double-sends to the same person.
- `announcements:send-due --limit=100` is scheduled every minute.
- "Send now" announcements go out within a minute (the status shows
  "Sending SMS..." until done).

### 4. Real Arkesel response checking + automatic format fallback
- The gateway response body is now validated: `{"code": "ok"}` means success;
  any other code (102 auth failed, 103 bad number, 105 no balance, 106 bad
  sender ID, 111 spam word, ...) is recorded as **failed** with a
  human-readable reason in the SMS log (Admin > Logs).
- Each number is tried first in local format (0244xxxxxx), then automatically
  retried in international format (233244xxxxxx) if the gateway rejects it.

### 5. Better logs
- `sms_logs` now has an `announcement_id` column (new migration) so
  announcement SMS can be told apart from voucher SMS, and every failed SMS
  shows the exact Arkesel error message.

### 6. Test command
- `php artisan sms:test 0244xxxxxx "Hello"` sends one test SMS so you can
  verify the key and number format from the server right after deploying.

## Deploy steps (in this order)
1. Upload the changed files.
2. Run: `php artisan migrate` (adds `sms_logs.announcement_id`).
3. **Make sure the scheduler cron exists and runs every minute:**
   `* * * * * php /path/to/your/app/artisan schedule:run >> /dev/null 2>&1`
   This is now what actually delivers expiry + announcement SMS. If you don't
   have it, nothing will be sent.
4. Queue worker is optional now (still fine to keep for emails).
5. Check `ARKESEL_SMS_API_KEY` in the server `.env` is the real key from
   sms.arkesel.com/user/sms-api/info — if the key were wrong, every SMS log
   row now says exactly that ("Authentication failed").
6. `php artisan optimize:clear`

## How to test after deploying
1. Run `php artisan sms:test 0244xxxxxxx "Hello"` on the server → confirms
   the key, sender ID and number format are working end-to-end.
2. Do a manual WiFi purchase → the voucher SMS should arrive within seconds.
3. Publish an announcement with "Send as SMS > Send now" → it arrives within
   a minute (batch is picked up by the cron).
4. Open Admin > Logs → SMS delivery log: every row is either "sent" or
   "failed" with the exact Arkesel reason.

## File list (only the files changed in this fix)
### New files
| File | Purpose |
|---|---|
| `app/Services/AnnouncementSmsSender.php` | Direct, batch, resume-safe announcement SMS sending |
| `app/Console/Commands/TestSms.php` | `php artisan sms:test` — one-off test SMS |
| `database/migrations/2026_08_13_000003_add_announcement_id_to_sms_logs_table.php` | Adds `sms_logs.announcement_id` |

### Modified files
| File | What changed |
|---|---|
| `app/Services/SmsService.php` | Validates the real Arkesel response body; human-readable error codes; local-format-first with automatic 233 fallback; announcement_id on log rows |
| `app/Models/Announcement.php` | `dueForSms` now includes "send now" (no schedule) and unfinished blasts; `smsLogs()` relation; `pendingSmsRecipients()` + `markSmsFinishedIfDone()` for resume-safe blasts |
| `app/Models/SmsLog.php` | `announcement_id` fillable + relation |
| `app/Http/Controllers/Admin/AdminController.php` | Manual purchase sends voucher SMS directly; announcement "send now" no longer needs the queue |
| `app/Services/PaymentFulfillmentService.php` | Online purchase sends voucher SMS directly |
| `app/Console/Commands/ExpireSubscriptions.php` | Expiry SMS sent directly in the cron run |
| `app/Console/Commands/SendDueAnnouncements.php` | Sends directly in batches (limit flag), no queue |
| `app/Jobs/SendAnnouncementSms.php` | Now a thin wrapper around `AnnouncementSmsSender` (legacy queued rows still work) |
| `routes/console.php` | Schedules `announcements:send-due --limit=100` every minute |
| `resources/views/admin/announcements.blade.php` | "Sending SMS..." status badge |
| `.env.example` | Cron requirement documented |

### Unchanged legacy files (no longer dispatched, kept for old queued rows)
- `app/Jobs/SendSubscriptionCredentialsSms.php`
- `app/Jobs/SendExpiryNotificationSms.php`
