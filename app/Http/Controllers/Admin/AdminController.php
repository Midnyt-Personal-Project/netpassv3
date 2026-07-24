<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Location;
use App\Models\Package;
use App\Models\Payment;
use App\Services\MikroTikService;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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
            'duration_minutes' => 'required|integer|min:1',
            'speed_limit_up' => 'nullable|string|max:30',
            'speed_limit_down' => 'nullable|string|max:30',
            'data_limit_mb' => 'nullable|integer|min:1',
        ]);
        $this->ensureManagedLocation((int) $data['location_id']);
        Package::create($data);

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

        return back()->with('success', "Device status changed to {$device->status}.");
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
    public function createSubscription(Request $request, MikroTikService $mikrotik, SmsService $sms)
    {
        $data = $request->validate([
            'location_id' => 'required|integer|exists:locations,id',
            'package_id' => 'required|integer|exists:packages,id',
            'phone_number' => 'required|string|max:30',
            'username' => 'nullable|string|max:50',
        ]);
        $location = $this->ensureManagedLocation((int) $data['location_id']);
        $package = Package::whereKey($data['package_id'])->where('location_id', $location->id)->firstOrFail();
        $customer = Customer::where('location_id', $location->id)->where('phone_number', $data['phone_number'])->first();

        if (!$customer) {
            $username = $data['username'] ?: $this->uniqueUsername();
            abort_if(Customer::where('username', $username)->exists(), 422, 'That username is already in use.');
            $customer = Customer::create([
                'location_id' => $location->id,
                'username' => $username,
                'password' => (string) random_int(1000, 9999),
                'phone_number' => $data['phone_number'],
                'status' => 'active',
            ]);
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
        ]);
        $router = $location->routers()->where('status', 'online')->first() ?: $location->routers()->first();
        if ($router) {
            $mikrotik->queueCreateUser($router, $customer->fresh());
        }
        $sms->sendCredentials($customer->fresh(), $package->name);

        return back()->with('success', "Subscription created for {$customer->username} through {$customer->expires_at->format('M j, Y g:i A')}.");
    }

    private function uniqueUsername(): string
    {
        do {
            $username = 'OY'.random_int(100000, 999999);
        } while (Customer::where('username', $username)->exists());

        return $username;
    }
}
