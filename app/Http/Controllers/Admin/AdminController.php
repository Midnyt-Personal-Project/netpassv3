<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, Announcement, Customer, Device, EmailLog, Location, Package, Payment, SmsLog};
use App\Services\{ActivityLogger, MikroTikService, OwnerNotificationService, SmsService};

class AdminController extends Controller
{
    /** A super admin can operate every location; an admin is restricted to their own. */
    protected function getAdminLocations()
    {
        $user = Auth::user();

        return $user->isSuperAdmin()
            ? Location::orderBy('name')->get()
            : $user->locations()->orderBy('name')->get();
    }

    protected function locationIds()
    {
        return $this->getAdminLocations()->pluck('id');
    }

    protected function ensureManagedLocation(int $locationId): Location
    {
        abort_unless($this->locationIds()->contains($locationId), 403, 'You cannot manage this location.');

        return Location::findOrFail($locationId);
    }

    public function dashboard()
    {
        $locations = $this->getAdminLocations();
        $locationIds = $locations->pluck('id');
        $stats = [
            'total_packages' => Package::whereIn('location_id', $locationIds)->count(),
            'total_customers' => Customer::whereIn('location_id', $locationIds)->count(),
            'active_customers' => Customer::whereIn('location_id', $locationIds)->where('status', 'active')->where('expires_at', '>', now())->count(),
            'total_revenue' => Payment::whereIn('location_id', $locationIds)->where('status', 'success')->sum('amount'),
            'total_devices' => Device::whereHas('customer', fn ($query) => $query->whereIn('location_id', $locationIds))->count(),
        ];
        $payments = Payment::with(['customer', 'package', 'location'])->whereIn('location_id', $locationIds)->latest()->take(10)->get();
        $customers = Customer::with(['activePackage', 'location'])->whereIn('location_id', $locationIds)->latest()->take(10)->get();

        return view('admin.dashboard', compact('stats', 'payments', 'customers', 'locations'));
    }

    public function showPackages()
    {
        $locations = $this->getAdminLocations();
        $packages = Package::whereIn('location_id', $locations->pluck('id'))->with('location')->get();

        return view('admin.packages', compact('packages', 'locations'));
    }

    public function createPackage(Request $request)
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
        ]);
        $this->ensureManagedLocation((int) $data['location_id']);

        // A month is treated as 30 days for a predictable hotspot expiry time.
        $multiplier = ['minutes' => 1, 'hours' => 60, 'days' => 1440, 'months' => 43200][$data['duration_unit']];
        $data['duration_minutes'] = $data['duration_value'] * $multiplier;
        unset($data['duration_value'], $data['duration_unit']);
        $package = Package::create($data);
        app(ActivityLogger::class)->record('package.created', "Created package {$package->name} for {$package->location->name}.");

        return back()->with('success', 'Package created successfully.');
    }

    public function showDevices()
    {
        $locationIds = $this->locationIds();
        $devices = Device::whereHas('customer', fn ($query) => $query->whereIn('location_id', $locationIds))
            ->with('customer.location')->latest()->get();

        return view('admin.devices', compact('devices'));
    }

    public function toggleDeviceStatus($id, MikroTikService $mikrotik)
    {
        $device = Device::with('customer.location')->findOrFail($id);
        $this->ensureManagedLocation($device->customer->location_id);
        $device->update(['status' => $device->status === 'active' ? 'blocked' : 'active']);
        $router = $device->customer->location->routers()->first();

        if ($router) {
            $device->status === 'blocked'
                ? $mikrotik->queueRemoveMac($router, $device)
                : $mikrotik->queueAddMac($router, $device, $device->customer);
        }

        app(ActivityLogger::class)->record('device.status_changed', "{$device->name} ({$device->mac_address}) was {$device->status} at {$device->customer->location->name}.");

        return back()->with('success', "Device status changed to {$device->status}.");
    }

    public function updateSubscriptionNotifications(Request $request, Location $location)
    {
        $this->ensureManagedLocation($location->id);
        $location->update(['subscription_email_notifications' => $request->boolean('subscription_email_notifications')]);
        app(ActivityLogger::class)->record('location.email_notifications', "Subscription email alerts were ".($location->subscription_email_notifications ? 'enabled' : 'disabled')." for {$location->name}.");

        return back()->with('success', $location->subscription_email_notifications ? 'Subscription email notifications enabled.' : 'Subscription email notifications disabled.');
    }

    public function showAnnouncements()
    {
        $locations = $this->getAdminLocations();
        $locationIds = $locations->pluck('id');
        $announcements = Announcement::with('location')
            ->where(fn ($query) => $query->whereNull('location_id')->orWhereIn('location_id', $locationIds))
            ->latest()->take(30)->get();

        return view('admin.announcements', compact('locations', 'announcements'));
    }

    public function createAnnouncement(Request $request)
    {
        $data = $request->validate([
            'location_id' => 'nullable|integer|exists:locations,id',
            'title' => 'nullable|string|max:80',
            'message' => 'required|string|max:240',
            'priority' => 'nullable|integer|min:0|max:100',
            'ends_at' => 'nullable|date|after:now',
        ]);

        // A global ticker is reserved for the super admin; owners can post to their own locations.
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
        app(ActivityLogger::class)->record('ticker.published', "Published ".($announcement->location_id ? 'location' : 'global')." ticker: {$announcement->message}");

        return back()->with('success', empty($data['location_id']) ? 'Global news ticker published.' : 'Location news ticker published.');
    }

    public function showLogs()
    {
        $locations = $this->getAdminLocations();
        $locationIds = $locations->pluck('id');
        $smsLogs = SmsLog::with('customer.location')->whereHas('customer', fn ($query) => $query->whereIn('location_id', $locationIds))->latest()->take(100)->get();
        $emailLogs = EmailLog::with(['customer', 'location'])->whereIn('location_id', $locationIds)->latest()->take(100)->get();
        $activityLogs = Auth::user()->isSuperAdmin()
            ? ActivityLog::with('user')->latest()->take(100)->get()
            : ActivityLog::with('user')->where('user_id', Auth::id())->latest()->take(100)->get();

        return view('admin.logs', compact('smsLogs', 'emailLogs', 'activityLogs'));
    }

    public function showSubscriptions()
    {
        $locations = $this->getAdminLocations();
        $locationIds = $locations->pluck('id');
        $packages = Package::whereIn('location_id', $locationIds)->with('location')->orderBy('name')->get();
        $subscriptions = Payment::with(['customer', 'package', 'location'])
            ->whereIn('location_id', $locationIds)->where('status', 'success')->latest()->take(25)->get();

        return view('admin.subscriptions', compact('locations', 'packages', 'subscriptions'));
    }

    /** Create or renew a customer subscription without requiring online checkout. */
    public function createSubscription(Request $request, MikroTikService $mikrotik, SmsService $sms, OwnerNotificationService $ownerNotifications)
    {
        $data = $request->validate([
            'location_id' => 'required|integer|exists:locations,id',
            'package_id' => 'required|integer|exists:packages,id',
            'phone_number' => 'required|string|max:30',
            'device_name' => 'nullable|required_with:mac_address|string|max:50',
            'mac_address' => ['nullable', 'required_with:device_name', 'string', 'regex:/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/'],
        ]);
        $location = $this->ensureManagedLocation((int) $data['location_id']);
        $package = Package::whereKey($data['package_id'])->where('location_id', $location->id)->firstOrFail();
        $customer = Customer::where('location_id', $location->id)->where('phone_number', $data['phone_number'])->first();

        if (!$customer) {
            $voucher = $this->uniqueVoucher();
            $customer = Customer::create([
                'location_id' => $location->id,
                // MikroTik receives the voucher in both credential fields.
                'username' => $voucher,
                'password' => $voucher,
                'voucher_code' => $voucher,
                'phone_number' => $data['phone_number'],
                'status' => 'active',
            ]);
        }

        $macAddress = null;
        if (!empty($data['mac_address'])) {
            $macAddress = strtoupper(str_replace('-', ':', $data['mac_address']));
            abort_if($customer->devices()->count() >= 3, 422, 'This customer already has the maximum of 3 registered devices.');
            abort_if(Device::where('mac_address', $macAddress)->exists(), 422, 'This MAC address is already registered to another customer.');
        }

        $start = $customer->expires_at?->isFuture() ? $customer->expires_at : Carbon::now();
        $customer->update(['active_package_id' => $package->id, 'expires_at' => $start->copy()->addMinutes($package->duration_minutes), 'status' => 'active']);
        $payment = Payment::create([
            'location_id' => $location->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'amount' => $package->price,
            'paystack_reference' => 'MANUAL-'.strtoupper(Str::random(14)),
            'status' => 'success',
            'platform_commission' => $package->price * ($location->commission_percentage / 100),
            'paystack_fee' => 0, // This subscription was issued manually, not charged by Paystack.
        ]);
        $router = $location->routers()->where('status', 'online')->first() ?: $location->routers()->first();
        $customer = $customer->fresh();
        if ($router) {
            $mikrotik->queueCreateUser($router, $customer);
        }

        if ($macAddress) {
            $device = Device::create(['customer_id' => $customer->id, 'mac_address' => $macAddress, 'name' => $data['device_name'], 'status' => 'active']);
            if ($router) {
                $mikrotik->queueAddMac($router, $device, $customer);
            }
        }

        $customer = $customer->fresh();
        $sms->sendCredentials($customer, $package->name);
        $ownerNotifications->subscriptionCreated($location->loadMissing('admin'), $customer, $package, $payment);
        app(ActivityLogger::class)->record('subscription.created', "Created {$package->name} subscription for voucher {$customer->voucher_code} at {$location->name}.");

        return back()->with('success', "Subscription created for {$customer->username} through {$customer->expires_at->format('M j, Y g:i A')}.");
    }

    private function uniqueVoucher(): string
    {
        do {
            $voucher = 'OY-'.strtoupper(Str::random(8));
        } while (Customer::where('voucher_code', $voucher)->exists());

        return $voucher;
    }
}
