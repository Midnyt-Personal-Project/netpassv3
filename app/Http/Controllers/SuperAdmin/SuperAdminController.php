<?php

namespace App\Http\Controllers\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use App\Http\Controllers\Controller;
use App\Models\{Location, Payment, Router, User};
use App\Services\{ActivityLogger, MikroTikService};

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
            'owner_payout_total' => $totalSales - $platformCommission - $paystackFees,
        ];

        $recent_payments = Payment::with(['location', 'customer', 'package'])
                                  ->orderBy('created_at', 'desc')
                                  ->take(10)
                                  ->latest()->paginate(15);

        $locations = Location::with(['admin', 'routers'])->latest()->paginate(15);
        $admins = User::where('role', 'admin')->with('locations')->latest()->get();

        return view('superadmin.dashboard', compact('stats', 'recent_payments', 'locations', 'admins'));
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

    public function createRouter(Request $request, MikroTikService $mikrotik)
    {
        $request->validate([
            'location_id' => 'required|exists:locations,id',
            'name' => 'required|string',
        ]);

        do {
            $routerId = 'RTR-'.random_int(100000, 999999);
        } while (Router::where('router_id', $routerId)->exists());

        do {
            $token = 'oyalo_'.Str::random(48);
        } while (Router::where('api_token', $token)->exists());

        $router = Router::create([
            'location_id' => $request->location_id,
            'router_id' => $routerId,
            'api_token' => $token,
            'name' => $request->name,
            'status' => 'offline'
        ]);

        foreach ($router->location->packages()->get() as $package) {
            $mikrotik->queueCreateProfile($router, $package);
        }

        app(ActivityLogger::class)->record('router.created', "Created router {$router->router_id} for location ID {$router->location_id}.");

        return back()->with('success', 'Router created successfully. Generated Token: '.$token);
    }

    /**
     * Display a paginated list of all routers.
     */
    public function showRouters()
    {
        $routers = \App\Models\Router::with('location')->latest()->paginate(15);
        return view('superadmin.routers', compact('routers'));
    }

    /**
     * Display a paginated list of all router commands.
     */
    public function showRouterCommands()
    {
        $commands = \App\Models\RouterCommand::with('router')->latest()->paginate(15);
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

    /**
     * Update an admin's info (name, email, phone) and optionally reset the password.
     */
    public function updateAdmin(Request $request, User $admin)
    {
        abort_unless($admin->role === 'admin', 404);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($admin->id)],
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6',
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $admin->update($data);

        app(ActivityLogger::class)->record('admin.updated', "Updated admin account {$admin->email}.");

        return back()->with('success', "Admin {$admin->name} was updated.");
    }
}