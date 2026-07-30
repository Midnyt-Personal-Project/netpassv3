<?php

namespace App\Http\Controllers\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use App\Http\Controllers\Controller;
use App\Models\{Location, Payment, Router, User};
use App\Services\ActivityLogger;

class SuperAdminController extends Controller
{
    public function dashboard()
    {
        $paidPayments = Payment::where('status', 'success');
        $totalSales = (float) (clone $paidPayments)->sum('amount');
        $platformCommission = (float) (clone $paidPayments)->sum('platform_commission');
        $paystackFees = (float) (clone $paidPayments)->sum('paystack_fee');

        $stats = [
            'total_admins' => User::where('role', 'admin')->count(),
            'total_locations' => Location::count(),
            'total_routers' => Router::count(),
            'total_sales' => $totalSales,
            'total_commission' => $platformCommission,
            'total_paystack_fees' => $paystackFees,
            // The amount due to location owners after platform commission and Paystack charges.
            'owner_payout_total' => $totalSales - $platformCommission - $paystackFees,
        ];

        $recent_payments = Payment::with(['location', 'customer', 'package'])
                                  ->orderBy('created_at', 'desc')
                                  ->take(10)
                                  ->latest()->paginate(15);

        $locations = Location::with(['admin', 'routers'])->latest()->paginate(15);

        return view('superadmin.dashboard', compact('stats', 'recent_payments', 'locations'));
    }

    public function createAdmin(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
        ]);

        $admin = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'admin',
            'status' => 'active'
        ]);

        app(ActivityLogger::class)->record('admin.created', "Created admin account {$admin->email}.");

        return back()->with('success', 'Admin account created successfully.');
    }

    public function createLocation(Request $request)
    {
        $request->validate([
            'admin_id' => 'required|exists:users,id',
            'name' => 'required|string',
            'commission_percentage' => 'required|numeric|min:0|max:100',
            'paystack_subaccount' => 'nullable|string',
            'subscription_email_notifications' => 'nullable|boolean',
        ]);

        // Locations can only belong to business-admin accounts.
        User::whereKey($request->admin_id)->where('role', 'admin')->firstOrFail();

        $location = Location::create([
            'admin_id' => $request->admin_id,
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'commission_percentage' => $request->commission_percentage,
            'paystack_subaccount' => $request->paystack_subaccount,
            'subscription_email_notifications' => $request->boolean('subscription_email_notifications'),
            'status' => 'active',
        ]);

        app(ActivityLogger::class)->record('location.created', "Created location {$location->name}.");

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

        $router = Router::create([
            'location_id' => $request->location_id,
            'router_id' => $routerId,
            'api_token' => $token,
            'name' => $request->name,
            'status' => 'offline'
        ]);

        app(ActivityLogger::class)->record('router.created', "Created router {$router->router_id} for location ID {$router->location_id}.");

        return back()->with('success', 'Router created successfully. Generated Token: ' . $token);
    }

    public function showRouters()
    {
        $routers = \App\Models\Router::with('location')->latest()->paginate(15);
        return view('superadmin.routers', compact('routers'));
    }

    public function showRouterCommands()
    {
        $commands = \App\Models\RouterCommand::with('router')->latest()->latest()->paginate(15);
        return view('superadmin.router_commands', compact('commands'));
    }

    public function toggleAdminStatus($id)
    {
        $admin = User::whereKey($id)->where('role', 'admin')->firstOrFail();
        $admin->status = $admin->status === 'active' ? 'suspended' : 'active';
        $admin->save();
        app(ActivityLogger::class)->record('admin.status_changed', "Admin {$admin->email} is now {$admin->status}.");

        return back()->with('success', "Admin status updated to {$admin->status}.");
    }
}
