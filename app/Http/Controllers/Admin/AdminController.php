<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Package;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    /**
     * Get locations belonging to logged-in admin.
     */
    protected function getAdminLocations()
    {
        // For testing/mocking, if not authenticated, we can return some location
        $admin = Auth::user();
        if ($admin) {
            return $admin->locations;
        }
        return Location::take(1)->get(); // Fallback
    }

    public function dashboard()
    {
        $locations = $this->getAdminLocations();
        $locationIds = $locations->pluck('id');

        $stats = [
            'total_packages' => Package::whereIn('location_id', $locationIds)->count(),
            'total_customers' => Customer::whereIn('location_id', $locationIds)->count(),
            'active_customers' => Customer::whereIn('location_id', $locationIds)->where('status', 'active')->count(),
            'total_revenue' => Payment::whereIn('location_id', $locationIds)->where('status', 'success')->sum('amount'),
            'total_devices' => Device::whereHas('customer', function ($query) use ($locationIds) {
                $query->whereIn('location_id', $locationIds);
            })->count(),
        ];

        $payments = Payment::with(['customer', 'package', 'location'])
                           ->whereIn('location_id', $locationIds)
                           ->orderBy('created_at', 'desc')
                           ->take(10)
                           ->get();

        $customers = Customer::with(['activePackage', 'location'])
                             ->whereIn('location_id', $locationIds)
                             ->orderBy('created_at', 'desc')
                             ->take(10)
                             ->get();

        return view('admin.dashboard', compact('stats', 'payments', 'customers', 'locations'));
    }

    public function showPackages()
    {
        $locations = $this->getAdminLocations();
        $locationIds = $locations->pluck('id');
        $packages = Package::whereIn('location_id', $locationIds)->with('location')->get();

        return view('admin.packages', compact('packages', 'locations'));
    }

    public function createPackage(Request $request)
    {
        $request->validate([
            'location_id' => 'required|exists:locations,id',
            'name' => 'required|string',
            'price' => 'required|numeric',
            'duration_minutes' => 'required|integer|min:1',
            'speed_limit_up' => 'nullable|string',
            'speed_limit_down' => 'nullable|string',
            'data_limit_mb' => 'nullable|integer',
        ]);

        Package::create($request->all());

        return back()->with('success', 'Package created successfully.');
    }

    public function showDevices()
    {
        $locations = $this->getAdminLocations();
        $locationIds = $locations->pluck('id');

        $devices = Device::whereHas('customer', function ($query) use ($locationIds) {
            $query->whereIn('location_id', $locationIds);
        })->with('customer.location')->orderBy('created_at', 'desc')->get();

        return view('admin.devices', compact('devices'));
    }

    public function toggleDeviceStatus($id)
    {
        $device = Device::findOrFail($id);
        $device->status = $device->status === 'active' ? 'blocked' : 'active';
        $device->save();

        // In a real scenario, this would trigger a REMOVE_MAC / ADD_MAC command
        $customer = $device->customer;
        $router = $customer->location->routers()->first();
        if ($router) {
            $mikrotik = new \App\Services\MikroTikService();
            if ($device->status === 'blocked') {
                $mikrotik->queueRemoveMac($router, $device);
            } else {
                $mikrotik->queueAddMac($router, $device, $customer);
            }
        }

        return back()->with('success', "Device status changed to {$device->status}.");
    }
}
