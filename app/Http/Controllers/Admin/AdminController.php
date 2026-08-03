<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Support\Str;

use App\Http\Controllers\Controller;
use App\Jobs\{SendOwnerSubscriptionEmail, SendSubscriptionCredentialsSms};
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
        $announcements = Announcement::with('location')
            ->where(fn ($query) => $query->whereNull('location_id')->orWhereIn('location_id', $locationIds))
            ->latest()
            ->paginate(15);

        return view('admin.announcements', compact('locations', 'announcements'));
    }

    /**
     * Create a new announcement (global or location-specific).
     */
    public function createAnnouncement(Request $request)
    {
        $data = $request->validate([
            'location_id' => 'nullable|integer|exists:locations,id',
            'title' => 'nullable|string|max:80',
            'message' => 'required|string|max:240',
            'priority' => 'nullable|integer|min:0|max:100',
            'ends_at' => 'nullable|date|after:now',
        ]);

        // Global ticker reserved for super admin only
        if (empty($data['location_id'])) {
            abort_unless(Auth::user()->isSuperAdmin(), 403, 'Only the super admin can publish global news.');
        } else {
            $this->ensureManagedLocation((int) $data['location_id']);
        }

        $announcement = Announcement::create([
            ...$data,
            'created_by' => Auth::id(),
            'is_active' => true,
            'priority' => $data['priority'] ?? 0,
        ]);

        app(ActivityLogger::class)->record(
            'ticker.published',
            "Published " . ($announcement->location_id ? 'location' : 'global') . " ticker: {$announcement->message}"
        );

        return back()->with(
            'success',
            empty($data['location_id']) ? 'Global news ticker published.' : 'Location news ticker published.'
        );
    }

    /**
     * Show logs (SMS, email, activity) with pagination.
     */
    public function showLogs()
    {
        $locations = $this->getAdminLocations();
        $locationIds = $locations->pluck('id');

        $smsLogs = SmsLog::with('customer.location')
            ->whereHas('customer', fn ($query) => $query->whereIn('location_id', $locationIds))
            ->latest()
            ->paginate(15);

        $emailLogs = EmailLog::with(['customer', 'location'])
            ->whereIn('location_id', $locationIds)
            ->latest()
            ->paginate(15);

        $activityLogs = Auth::user()->isSuperAdmin()
            ? ActivityLog::with('user')->latest()->paginate(15)
            : ActivityLog::with('user')->where('user_id', Auth::id())->latest()->paginate(15);

        return view('admin.logs', compact('smsLogs', 'emailLogs', 'activityLogs'));
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

        SendSubscriptionCredentialsSms::dispatch($payment->id);
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

}
