# Update Notes — Announcements / SMS / Packages / Accounts (2026-08-13)

Everything in this update. Apply ALL files below to your publish (production) system,
then run the migration commands at the bottom.

## What changed

### 1. Announcements — send by SMS to one person or everyone + schedule/pause/delete
- `Announcements` page (`/admin/announcements`) can now publish a message as:
  - a portal/TV ticker only,
  - an SMS only, or
  - both at the same time.
- SMS recipients:
  - **One customer** — type a phone number (024xxxxxxx / 233xxxxxxxx) or a voucher code.
  - **Everyone** — every customer at the selected location. Super admin can pick
    "All locations — global" and message every customer on the platform.
- Sending time:
  - **Send now** — goes out immediately through the queue.
  - **Schedule for later** — a scheduler (`announcements:send-due`, every minute)
    sends it automatically at the chosen date/time.
- Every published item can be:
  - **Paused / Resumed** (pausing hides the ticker and cancels a scheduled SMS
    that hasn't gone out yet),
  - **Rescheduled to a new date** (already-sent blasts will send again on the new date),
  - **Deleted**.

### 2. Edit packages
- `Packages` page (`/admin/packages`) now has an **Edit** button on every plan.
  You can rename the plan, change price, duration, speeds, data cap and shared users.
  The updated profile is re-synced to all routers at that location automatically.

### 3. Edit admin / account info (Paystack account etc.)
- New **My account** page (`/admin/settings`, linked in the sidebar):
  - Admin can update their own **name, email, phone number, and password**.
- **Paystack subaccounts are super admin only.** On the same page the super admin
  can update the Paystack subaccount of every location. Regular admins see a
  "managed by the super admin" notice instead and cannot change them.
- Super admin dashboard has a new **Manage Admins** section:
  - Edit any admin's name, email, phone, reset their password,
  - Suspend / activate admins (existing button, now with a list).

### 4. SMS — removed the 233 prefix
- All SMS sent through the gateway now convert `233XXXXXXXXX` to local format
  `0XXXXXXXXX` (e.g. 0244123456), which fixes the slow / failed delivery.
- Turned ON by default. To go back to the old behaviour, set
  `ARKESEL_SMS_LOCAL_FORMAT=false` in the `.env` on the server.

### 5. Login button loading state
- The **Sign In** button now shows a spinner + "Signing in..." and disables
  itself while the login is being processed, so people don't tap it twice.

## Full file list (add/overwrite these on your publish system)

### New files
| File | Purpose |
|---|---|
| `app/Jobs/SendAnnouncementSms.php` | Queue job that sends an announcement SMS blast (atomic claim = no double sends) |
| `app/Console/Commands/SendDueAnnouncements.php` | Command that fires scheduled SMS blasts when their time arrives |
| `database/migrations/2026_08_13_000001_enhance_announcements_for_sms.php` | Adds `show_ticker`, `send_sms`, `customer_id`, `scheduled_at`, `sent_at` to `announcements` |
| `database/migrations/2026_08_13_000002_add_phone_to_users_table.php` | Adds `phone` column to `users` |
| `resources/views/admin/settings.blade.php` | New "My account" page (profile for everyone; Paystack accounts visible/editable to super admin only) |

### Modified files
| File | What changed |
|---|---|
| `app/Http/Controllers/Admin/AdminController.php` | Announcement create (SMS + schedule + recipients), pause/resume, reschedule, delete; `updatePackage`; account settings + Paystack update methods (Paystack update is super admin only) |
| `app/Http/Controllers/SuperAdmin/SuperAdminController.php` | `updateAdmin` method; dashboard now passes the admins list |
| `app/Models/Announcement.php` | New fields, casts, `customer()` relation, `dueForSms()` scope, `visible()` now respects `show_ticker` |
| `app/Models/User.php` | `phone` added to fillable |
| `app/Services/SmsService.php` | Gateway number formatting now strips the `233` prefix (local format) |
| `config/services.php` | Added `arkesel.local_format` config flag |
| `routes/web.php` | New routes for package update, announcement actions, account settings |
| `routes/console.php` | Scheduled `announcements:send-due` every minute |
| `resources/views/admin/announcements.blade.php` | Fully rebuilt form + list with SMS options and pause/reschedule/delete buttons |
| `resources/views/admin/packages.blade.php` | Edit button + inline edit form per package |
| `resources/views/auth/login.blade.php` | Sign In button loading state |
| `resources/views/layouts/app.blade.php` | Sidebar: renamed to "Announcements", added "My account" link |
| `resources/views/superadmin/dashboard.blade.php` | New "Manage Admins" section with edit forms |
| `.env.example` | Added `ARKESEL_SMS_LOCAL_FORMAT=true` (documentation only) |

## Deploy steps (in this order)
1. Upload all files above to the server.
2. Run: `php artisan migrate`
3. Make sure your cron still runs every minute: `* * * * * php /path/to/app/artisan schedule:run` (already required for the existing expiry job).
4. Make sure the queue worker is running (already required for voucher SMS): `php artisan queue:work`
5. Add to the server `.env` (optional): `ARKESEL_SMS_LOCAL_FORMAT=true` (default is already true).
6. Clear caches: `php artisan optimize:clear`

## Notes / defaults
- Existing announcements keep working: `show_ticker` defaults to **true** and
  `send_sms` to **false** for all old rows.
- An announcement must have at least one channel (ticker or SMS) selected, otherwise
  the form shows an error.
- SMS blasts never retry (retrying would send duplicates), and the sending job
  claims the announcement atomically so a scheduled run + manual dispatch can
  never double-send.
- The customer portal ticker only shows announcements with `show_ticker = true`.
