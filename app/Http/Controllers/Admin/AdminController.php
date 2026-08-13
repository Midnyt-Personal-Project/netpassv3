<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Hash};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use App\Http\Controllers\Controller;
use App\Jobs\SendOwnerSubscriptionEmail;
use App\Models\{ActivityLog, Announcement, Customer, Device, EmailLog, Location, Package, Payment, RouterCommand, SmsLog};
use App\Services\{ActivityLogger, MikroTikService, OwnerNotificationService, SmsService, SubscriptionIssuer};
use App\Support\PhoneNumber;


class AdminController extends Controller
{
    /**
     * Get all locations the current admin can manage.
     * Super admin sees all; regular admin sees only assigned locations.
     */
    protected function getAdminLocations()
    {
        $user = Auth::user();

        return $user->isSuperAdmin()
            ? Location::orderBy('name')->get()
            : $user->locations()->orderBy('name')->latest()->paginate(15);
    }

    /**
     * Get the IDs of the locations the admin can manage.
     */
    protected function locationIds()
    {
        return $this->getAdminLocations()->pluck('id');
    }

    /**
     * Ensure the admin has permission to manage a specific location.
     */
    protected function ensureManagedLocation(int $locationId): Location
    {
        abort_unless($this->locationIds()->contains($locationId), 403, 'You cannot manage this location.');
        return Location::findOrFail($locationId);
    }

    /**
     * Dashboard with statistics and paginated tables with search.
     */
    public function dashboard(Request $request)
    {
        $locations = $this->getAdminLocations();
        $locationIds = $locations->pluck('id');

        // Statistics
        $stats = [
            'total_packages' => Package::whereIn('location_id', $locationIds)->count(),
            'total_customers' => Customer::whereIn('location_id', $locationIds)->count(),
            'active_customers' => Customer::whereIn('location_id', $locationIds)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->count(),
            'total_revenue' => Payment::whereIn('location_id', $locationIds)
                ->where('status', 'success')
                ->sum('amount'),
            'total_devices' => Device::whereHas('customer', fn ($q) => $q->whereIn('location_id', $locationIds))->count(),
        ];

        // Payments – with search
        $paymentSearch = $request->query('payment_search');
        $paymentsQuery = Payment::with(['customer', 'package', 'location'])
            ->whereIn('location_id', $locationIds)
            ->where('status', 'success');

        if ($paymentSearch) {
            $paymentsQuery->where(function ($q) use ($paymentSearch) {
                $q->where('paystack_reference', 'LIKE', "%{$paymentSearch}%")
                    ->orWhereHas('package', fn ($p) => $p->where('name', 'LIKE', "%{$paymentSearch}%"))
                    ->orWhereHas('customer', fn ($c) => $c->where('username', 'LIKE', "%{$paymentSearch}%")
                        ->orWhere('phone_number', 'LIKE', "%{$paymentSearch}%"));
            });
        }

        $payments = $paymentsQuery->latest()->paginate(10, ['*'], 'payment_page');

        // Customers – with search
        $customerSearch = $request->query('customer_search');
        $customersQuery = Customer::with(['activePackage', 'location'])
            ->whereIn('location_id', $locationIds);

        if ($customerSearch) {
            $customersQuery->where(function ($q) use ($customerSearch) {
                $q->where('username', 'LIKE', "%{$customerSearch}%")
                    ->orWhere('phone_number', 'LIKE', "%{$customerSearch}%")
                    ->orWhere('voucher_code', 'LIKE', "%{$customerSearch}%")
                    ->orWhereHas('activePackage', fn ($p) => $p->where('name', 'LIKE', "%{$customerSearch}%"));
            });
        }

        $customers = $customersQuery->latest()->paginate(10, ['*'], 'customer_page');

        return view('admin.dashboard', compact('stats', 'payments', 'customers', 'locations'));
    }

    /**
     * List all packages with pagination.
     */
    public function showPackages()
    {
        $locations = $this->getAdminLocations();
        $packages = Package::whereIn('location_id', $locations->pluck('id'))
            ->with('location')
            ->latest()
            ->paginate(15);

        return view('admin.packages', compact('packages', 'locations'));
    }

    /**
     * Create a new package and queue the profile creation on the router.
     */
    public function createPackage(Request $request, MikroTikService $mikrotik)
    {
        $data = $request->validate([
            'location_id' => 'required|integer|exists:locations,id',
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'duration_value' => 'required|integer|min:1|max:999',
            'duration_unit' => 'required|in:minutes,hours,days,months',
            'speed_limit_up' => 'nullable|string|max:30',
            'speed_limit_down' => 'nullable|string|max:30',
            'data_limit_mb' => 'nullable|integer|min:1',
            'share_users' => 'nullable|integer|min:1|max:100',
        ]);

        $this->ensureManagedLocation((int) $data['location_id']);

        // Calculate duration in minutes
        $multiplier = [
            'minutes' => 1,
            'hours' => 60,
            'days' => 1440,
            'months' => 43200,
        ][$data['duration_unit']];

        $data['duration_minutes'] = $data['duration_value'] * $multiplier;
        unset($data['duration_value'], $data['duration_unit']);

        $package = Package::create($data);

        // Every router at the location needs the package profile.
        foreach ($package->location->routers()->get() as $router) {
            $mikrotik->queueCreateProfile($router, $package);
        }

        app(ActivityLogger::class)->record(
            'package.created',
            "Created package {$package->name} for {$package->location->name}."
        );

        return back()->with('success', 'Package created successfully.');
    }

    /**
     * Update an existing package (name, price, speed, data cap...) and
     * re-queue its profile on every router at the location.
     */
    public function updatePackage(Request $request, Package $package, MikroTikService $mikrotik)
    {
        $this->ensureManagedLocation($package->location_id);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'duration_value' => 'required|integer|min:1|max:999',
            'duration_unit' => 'required|in:minutes,hours,days,months',
            'speed_limit_up' => 'nullable|string|max:30',
            'speed_limit_down' => 'nullable|string|max:30',
            'data_limit_mb' => 'nullable|integer|min:1',
            'share_users' => 'nullable|integer|min:1|max:100',
        ]);

        // Calculate duration in minutes
        $multiplier = [
            'minutes' => 1,
            'hours' => 60,
            'days' => 1440,
            'months' => 43200,
        ][$data['duration_unit']];

        $data['duration_minutes'] = $data['duration_value'] * $multiplier;
        unset($data['duration_value'], $data['duration_unit']);

        $package->update($data);

        // Re-sync the (possibly renamed) profile to every router.
        foreach ($package->location->routers()->get() as $router) {
            $mikrotik->queueCreateProfile($router, $package);
        }

        app(ActivityLogger::class)->record(
            'package.updated',
            "Updated package {$package->name} for {$package->location->name}."
        );

        return back()->with('success', 'Package updated successfully.');
    }

    /**
     * List all devices with pagination.
     */
    public function showDevices()
    {
        $locationIds = $this->locationIds();
        $devices = Device::whereHas('customer', fn ($query) => $query->whereIn('location_id', $locationIds))
            ->with('customer.location')
            ->latest()
            ->paginate(15);

        return view('admin.devices', compact('devices'));
    }

    /**
     * Toggle device status (active/blocked) and sync with router.
     */
    public function toggleDeviceStatus($id, MikroTikService $mikrotik)
    {
        $device = Device::with('customer.location')->findOrFail($id);
        $this->ensureManagedLocation($device->customer->location_id);

        $newStatus = $device->status === 'active' ? 'blocked' : 'active';
        $device->update(['status' => $newStatus]);

        foreach ($device->customer->location->routers()->get() as $router) {
            if ($newStatus === 'blocked') {
                $mikrotik->queueRemoveMac($router, $device);
            } else {
                $mikrotik->queueAddMac($router, $device, $device->customer);
            }
        }

        app(ActivityLogger::class)->record(
            'device.status_changed',
            "{$device->name} ({$device->mac_address}) was {$device->status} at {$device->customer->location->name}."
        );

        return back()->with('success', "Device status changed to {$device->status}.");
    }

    /**
     * Toggle subscription email notifications for a location.
     */
    public function updateSubscriptionNotifications(Request $request, Location $location)
    {
        $this->ensureManagedLocation($location->id);
        $enabled = $request->boolean('subscription_email_notifications');
        $location->update(['subscription_email_notifications' => $enabled]);

        app(ActivityLogger::class)->record(
            'location.email_notifications',
            "Subscription email alerts were " . ($enabled ? 'enabled' : 'disabled') . " for {$location->name}."
        );

        return back()->with(
            'success',
            $enabled ? 'Subscription email notifications enabled.' : 'Subscription email notifications disabled.'
        );
    }

    /**
     * List announcements with pagination.
     */
    public function showAnnouncements()
    {
        $locations = $this->getAdminLocations();
        $locationIds = $locations->pluck('id');
        $announcements = Announcement::with(['location', 'customer'])
            ->where(fn ($query) => $query->whereNull('location_id')->orWhereIn('location_id', $locationIds))
            ->latest()
            ->paginate(15);

        // Recipient counts shown next to the "everyone" option.
        $scopeCounts = [];
        if (Auth::user()->isSuperAdmin()) {
            $scopeCounts['global'] = Customer::count();
        }
        foreach ($locations as $location) {
            $scopeCounts[$location->id] = Customer::where('location_id', $location->id)->count();
        }

        return view('admin.announcements', compact('locations', 'announcements', 'scopeCounts', 'locationIds'));
    }

    /**
     * Find the customer an individual SMS should go to, by phone or voucher.
     */
    protected function resolveRecipientCustomer(string $input, ?int $locationId): ?Customer
    {
        $scope = $locationId
            ? Customer::where('location_id', $locationId)
            : Customer::whereIn('location_id', $this->locationIds());

        $query = clone $scope;

        $normalized = PhoneNumber::normalize($input);
        $customer = $normalized
            ? (clone $query)->where('phone_number', $normalized)->first()
            : null;

        if (!$customer) {
            $customer = (clone $query)->where('voucher_code', Str::upper(trim($input)))->first();
        }

        return $customer;
    }

    /**
     * Create a new announcement (global or location-specific) as a portal
     * ticker and/or an SMS blast, either now or scheduled for later.
     */
    public function createAnnouncement(Request $request)
    {
        $data = $request->validate([
            'location_id' => 'nullable|integer|exists:locations,id',
            'title' => 'nullable|string|max:80',
            'message' => 'required|string|max:240',
            'priority' => 'nullable|integer|min:0|max:100',
            'ends_at' => 'nullable|date|after:now',
            'show_ticker' => 'nullable|boolean',
            'send_sms' => 'nullable|boolean',
            'sms_recipient' => 'nullable|required_if:send_sms,1|in:all,one',
            'recipient_phone' => 'nullable|required_if:sms_recipient,one|string|max:30',
            'sms_schedule' => 'nullable|required_if:send_sms,1|in:now,later',
            'scheduled_at' => 'nullable|required_if:sms_schedule,later|date|after:now',
        ]);

        if (empty($data['show_ticker']) && empty($data['send_sms'])) {
            return back()
                ->withErrors(['send_sms' => 'Pick at least one: show it on the portal ticker, or send it by SMS.'])
                ->withInput();
        }

        // Global ticker reserved for super admin only
        $locationId = isset($data['location_id']) ? (int) $data['location_id'] : null;
        if ($locationId === null) {
            abort_unless(Auth::user()->isSuperAdmin(), 403, 'Only the super admin can publish global news.');
        } else {
            $this->ensureManagedLocation($locationId);
        }

        // Resolve the single recipient when "one customer" was chosen.
        $customer = null;
        if (!empty($data['send_sms']) && ($data['sms_recipient'] ?? null) === 'one') {
            $customer = $this->resolveRecipientCustomer($data['recipient_phone'], $locationId);

            if (!$customer) {
                return back()
                    ->withErrors(['recipient_phone' => 'No customer with that phone number or voucher was found in the selected location.'])
                    ->withInput();
            }
        }

        $sendSms = !empty($data['send_sms']);
        $scheduledAt = $sendSms && ($data['sms_schedule'] ?? null) === 'later'
            ? $data['scheduled_at']
            : null;

        $announcement = Announcement::create([
            'location_id' => $locationId,
            'created_by' => Auth::id(),
            'customer_id' => $customer?->id,
            'title' => $data['title'] ?? null,
            'message' => $data['message'],
            'priority' => $data['priority'] ?? 0,
            'ends_at' => $data['ends_at'] ?? null,
            'show_ticker' => !empty($data['show_ticker']),
            'send_sms' => $sendSms,
            'scheduled_at' => $scheduledAt,
            'is_active' => true,
        ]);

        // "Send now" blasts are picked up by the every-minute scheduler
        // (announcements:send-due). This works even without a queue worker.
        $detail = $sendSms
            ? ($scheduledAt
                ? 'SMS blast scheduled for '.$announcement->scheduled_at->format('M j, Y g:i A').'.'
                : ($customer
                    ? "SMS is being sent to {$customer->phone_number} (within a minute)."
                    : 'SMS blast to all customers is being sent (within a minute).'))
            : '';

        app(ActivityLogger::class)->record(
            'announcement.created',
            "Published ".($announcement->location_id ? 'location' : 'global')." announcement: {$announcement->message}. {$detail}"
        );

        return back()->with('success', 'Announcement published. '.$detail);
    }

    /**
     * Pause or resume an announcement (hides the ticker and cancels any
     * scheduled SMS blast that has not gone out yet).
     */
    public function toggleAnnouncement(Announcement $announcement)
    {
        $this->ensureCanManageAnnouncement($announcement);

        $paused = !$announcement->isPaused();
        $announcement->update(['is_active' => !$paused]);

        app(ActivityLogger::class)->record(
            'announcement.'.($paused ? 'paused' : 'resumed'),
            ($paused ? 'Paused' : 'Resumed')." announcement: {$announcement->message}"
        );

        return back()->with('success', $paused
            ? 'Announcement paused. Any scheduled SMS has been cancelled.'
            : 'Announcement resumed.');
    }

    /**
     * Move a scheduled SMS blast to a new date (or re-send an already sent one).
     */
    public function rescheduleAnnouncement(Request $request, Announcement $announcement)
    {
        $this->ensureCanManageAnnouncement($announcement);

        $data = $request->validate([
            'scheduled_at' => 'required|date|after:now',
        ]);

        $announcement->update([
            'scheduled_at' => $data['scheduled_at'],
            'sent_at' => null,
            'is_active' => true,
        ]);

        app(ActivityLogger::class)->record(
            'announcement.rescheduled',
            "Rescheduled announcement SMS to {$announcement->scheduled_at->format('M j, Y g:i A')}: {$announcement->message}"
        );

        return back()->with('success', 'SMS rescheduled for '.$announcement->scheduled_at->format('M j, Y g:i A').'.');
    }

    /**
     * Delete an announcement entirely.
     */
    public function deleteAnnouncement(Announcement $announcement)
    {
        $this->ensureCanManageAnnouncement($announcement);

        $announcement->delete();

        app(ActivityLogger::class)->record(
            'announcement.deleted',
            "Deleted announcement: {$announcement->message}"
        );

        return back()->with('success', 'Announcement deleted.');
    }

    /**
     * Only the super admin may manage global items; admins may manage items
     * belonging to one of their own locations.
     */
    protected function ensureCanManageAnnouncement(Announcement $announcement): void
    {
        if ($announcement->location_id === null) {
            abort_unless(Auth::user()->isSuperAdmin(), 403, 'Only the super admin can manage global announcements.');
            return;
        }

        $this->ensureManagedLocation($announcement->location_id);
    }

    /**
     * Show logs (SMS, email, activity) with pagination, summary stats and
     * filters so SMS delivery can be diagnosed at a glance.
     */
    public function showLogs(Request $request)
    {
        $locations = $this->getAdminLocations();
        $locationIds = $locations->pluck('id');
        $isSuper = Auth::user()->isSuperAdmin();

        $smsBase = SmsLog::with(['customer.location', 'announcement'])
            ->when(!$isSuper, fn ($query) => $query->whereHas('customer', fn ($query) => $query->whereIn('location_id', $locationIds)));

        // Summary: today + last 7 days, within the caller's scope.
        $smsStats = [
            'today_sent' => (clone $smsBase)->whereDate('created_at', today())->where('status', 'sent')->count(),
            'today_failed' => (clone $smsBase)->whereDate('created_at', today())->where('status', 'failed')->count(),
            'week_sent' => (clone $smsBase)->where('created_at', '>=', now()->subDays(7))->where('status', 'sent')->count(),
            'week_failed' => (clone $smsBase)->where('created_at', '>=', now()->subDays(7))->where('status', 'failed')->count(),
        ];

        $smsQuery = clone $smsBase;

        if ($request->filled('sms_status') && in_array($request->query('sms_status'), ['sent', 'failed'], true)) {
            $smsQuery->where('status', $request->query('sms_status'));
        }

        if ($request->filled('sms_search')) {
            $search = trim((string) $request->query('sms_search'));
            $smsQuery->where(function ($query) use ($search) {
                $query->where('phone_number', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhere('error_message', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%");
            });
        }

        $smsLogs = $smsQuery->latest()->paginate(15);

        $emailLogs = EmailLog::with(['customer', 'location'])
            ->whereIn('location_id', $locationIds)
            ->latest()
            ->paginate(15);

        $activityLogs = $isSuper
            ? ActivityLog::with('user')->latest()->paginate(15)
            : ActivityLog::with('user')->where('user_id', Auth::id())->latest()->paginate(15);

        return view('admin.logs', compact('smsLogs', 'smsStats', 'emailLogs', 'activityLogs'));
    }

    /**
     * Show subscriptions with pagination.
     */
    public function showSubscriptions()
    {
        $locations = $this->getAdminLocations();
        $locationIds = $locations->pluck('id');

        $packages = Package::whereIn('location_id', $locationIds)
            ->with('location')
            ->orderBy('name')
            ->get(); // for the dropdown form

        $subscriptions = Payment::with(['customer', 'package', 'location'])
            ->whereIn('location_id', $locationIds)
            ->where('status', 'success')
            ->latest()
            ->paginate(15);

        return view('admin.subscriptions', compact('locations', 'packages', 'subscriptions'));
    }

    /**
     * Create a new subscription manually (admin/owner) and sync with router.
     */
    public function createSubscription(
        Request $request,
        SubscriptionIssuer $issuer,
    )
    {
        $normalizedPhone = PhoneNumber::normalize($request->input('phone_number'));
        $request->merge(['phone_number' => $normalizedPhone ?? $request->input('phone_number')]);

        $data = $request->validate([
            'location_id' => 'required|integer|exists:locations,id',
            'package_id' => 'required|integer|exists:packages,id',
            'phone_number' => ['required', 'string', 'regex:/^233[0-9]{9}$/'],
            'device_name' => 'nullable|required_with:mac_address|string|max:50',
            'mac_address' => ['nullable', 'required_with:device_name', 'string', 'regex:/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/'],
        ], [
            'phone_number.regex' => 'Enter a valid Ghana phone number, for example 0244123456.',
        ]);

        $location = $this->ensureManagedLocation((int) $data['location_id']);
        $package = Package::whereKey($data['package_id'])->where('location_id', $location->id)->firstOrFail();
        $macAddress = isset($data['mac_address'])
            ? strtoupper(str_replace('-', ':', $data['mac_address']))
            : null;

        [$customer, $payment] = DB::transaction(function () use ($data, $location, $package, $macAddress, $issuer): array {
            // Each sale creates an independent voucher even when this phone
            // number has bought at the same location before.
            $customer = $issuer->issue(
                $location,
                $package,
                $data['phone_number'],
                $macAddress,
                $data['device_name'] ?? null,
            );

            $payment = Payment::create([
                'location_id' => $location->id,
                'customer_id' => $customer->id,
                'purchaser_phone' => $data['phone_number'],
                'requested_mac_address' => $macAddress,
                'requested_device_name' => $data['device_name'] ?? null,
                'package_id' => $package->id,
                'amount' => $package->price,
                'paystack_reference' => 'MANUAL-'.Str::upper(Str::random(16)),
                'status' => 'success',
                'processed_at' => now(),
                'platform_commission' => $package->price * ($location->commission_percentage / 100),
                'paystack_fee' => 0,
            ]);

            return [$customer, $payment];
        }, 3);

        // Voucher SMS is sent immediately (not through the queue) so it always
        // arrives even when no queue worker is running on the server.
        app(SmsService::class)->sendCredentials($customer, $package->name);
        SendOwnerSubscriptionEmail::dispatch($payment->id);

        app(ActivityLogger::class)->record(
            'subscription.created',
            "Created {$package->name} subscription for voucher {$customer->voucher_code} at {$location->name}."
        );

        return back()->with(
            'success',
            "New voucher {$customer->username} created through {$customer->expires_at->format('M j, Y g:i A')}."
        );
    }

    /**
     * Block a subscription (disable the user on the router).
     */
    public function blockSubscription($id, MikroTikService $mikrotik)
    {
        $customer = Customer::findOrFail($id);
        $this->ensureManagedLocation($customer->location_id);
        $customer->update(['status' => 'suspended']);

        foreach ($customer->location->routers()->get() as $router) {
            $mikrotik->queueDisableUser($router, $customer);

            foreach ($customer->activeDevices()->get() as $device) {
                $mikrotik->queueRemoveMac($router, $device);
            }
        }

        app(ActivityLogger::class)->record(
            'subscription.blocked',
            "Blocked subscription for {$customer->username} at {$customer->location->name}."
        );

        return back()->with('success', 'Subscription blocked successfully.');
    }

    /**
     * Unblock a subscription (re-enable the user and MAC TV on the router).
     */
    public function unblockSubscription($id, MikroTikService $mikrotik)
    {
        $customer = Customer::findOrFail($id);
        $this->ensureManagedLocation($customer->location_id);
        $customer->update(['status' => 'active']);

        foreach ($customer->location->routers()->get() as $router) {
            $mikrotik->queueCreateUser($router, $customer);

            foreach ($customer->activeDevices()->get() as $device) {
                $mikrotik->queueAddMac($router, $device, $customer);
            }
        }

        app(ActivityLogger::class)->record(
            'subscription.unblocked',
            "Unblocked subscription for {$customer->username} at {$customer->location->name}."
        );

        return back()->with('success', 'Subscription unblocked successfully.');
    }

    /**
     * Remove a subscription (remove the user from the router).
     */
    public function removeSubscription($id, MikroTikService $mikrotik)
    {
        $customer = Customer::findOrFail($id);
        $this->ensureManagedLocation($customer->location_id);
        $customer->update(['status' => 'expired']);

        foreach ($customer->location->routers()->get() as $router) {
            $mikrotik->queueRemoveUser($router, $customer);

            foreach ($customer->activeDevices()->get() as $device) {
                $mikrotik->queueRemoveMac($router, $device);
            }
        }

        app(ActivityLogger::class)->record(
            'subscription.removed',
            "Removed subscription for {$customer->username} at {$customer->location->name}."
        );

        return back()->with('success', 'Subscription removed successfully.');
    }

    /**
     * Account settings: the signed-in admin's own info. Paystack subaccounts
     * are super-admin only and are shown to super admins on the same page.
     */
    public function showSettings()
    {
        $user = Auth::user();
        $locations = $user->isSuperAdmin()
            ? Location::with('admin')->orderBy('name')->get()
            : collect();

        return view('admin.settings', compact('user', 'locations'));
    }

    /**
     * Update the signed-in user's own name, email, phone and password.
     */
    public function updateSettings(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6|confirmed',
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        app(ActivityLogger::class)->record(
            'account.updated',
            "{$user->email} updated their own account details."
        );

        return back()->with('success', 'Your account details were updated.');
    }

    /**
     * Update the Paystack subaccount of a location. Super admin only —
     * business owners cannot change their own payout account.
     */
    public function updateLocationPaystack(Request $request, Location $location)
    {
        abort_unless(Auth::user()->isSuperAdmin(), 403, 'Only the super admin can edit Paystack accounts.');

        $data = $request->validate([
            'paystack_subaccount' => 'nullable|string|max:100',
        ]);

        $location->update($data);

        app(ActivityLogger::class)->record(
            'location.paystack_updated',
            "Updated Paystack subaccount for {$location->name}."
        );

        return back()->with('success', "Paystack account for {$location->name} was updated.");
    }

}
