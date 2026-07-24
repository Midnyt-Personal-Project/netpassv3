<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Location;
use App\Models\Router;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperAdminController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'total_admins' => User::where('role', 'admin')->count(),
            'total_locations' => Location::count(),
            'total_routers' => Router::count(),
            'total_sales' => Payment::where('status', 'success')->sum('amount'),
            'total_commission' => Payment::where('status', 'success')->sum('platform_commission'),
        ];

        $recent_payments = Payment::with(['location', 'customer', 'package'])
                                  ->orderBy('created_at', 'desc')
                                  ->take(10)
                                  ->get();

        $locations = Location::with(['admin', 'routers'])->get();

        return view('superadmin.dashboard', compact('stats', 'recent_payments', 'locations'));
    }

    public function createAdmin(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'admin',
            'status' => 'active'
        ]);

        return back()->with('success', 'Admin account created successfully.');
    }

    public function createLocation(Request $request)
    {
        $request->validate([
            'admin_id' => 'required|exists:users,id',
            'name' => 'required|string',
            'commission_percentage' => 'required|numeric|min:0|max:100',
            'paystack_subaccount' => 'nullable|string',
        ]);

        Location::create([
            'admin_id' => $request->admin_id,
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'commission_percentage' => $request->commission_percentage,
            'paystack_subaccount' => $request->paystack_subaccount,
            'status' => 'active',
        ]);

        return back()->with('success', 'Location created successfully.');
    }

    public function createRouter(Request $request)
    {
        $request->validate([
            'location_id' => 'required|exists:locations,id',
            'name' => 'required|string',
        ]);

        // Generate Router ID e.g., RTR-123456
        $routerId = 'RTR-' . rand(100000, 999999);
        $token = 'oyalo_' . Str::random(32);

        Router::create([
            'location_id' => $request->location_id,
            'router_id' => $routerId,
            'api_token' => $token,
            'name' => $request->name,
            'status' => 'offline'
        ]);

        return back()->with('success', 'Router created successfully. Generated Token: ' . $token);
    }

    public function toggleAdminStatus($id)
    {
        $admin = User::findOrFail($id);
        $admin->status = $admin->status === 'active' ? 'suspended' : 'active';
        $admin->save();

        return back()->with('success', "Admin status updated to {$admin->status}.");
    }
}
