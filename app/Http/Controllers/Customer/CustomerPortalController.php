<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Location;
use App\Models\Package;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Device;
use App\Services\PaystackService;
use App\Services\SmsService;
use App\Services\ActivityLogger;
use App\Services\MikroTikService;
use App\Services\OwnerNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CustomerPortalController extends Controller
{
    protected $paystack;
    protected $sms;
    protected $mikrotik;
    protected $ownerNotifications;

    public function __construct(PaystackService $paystack, SmsService $sms, MikroTikService $mikrotik, OwnerNotificationService $ownerNotifications)
    {
        $this->paystack = $paystack;
        $this->sms = $sms;
        $this->mikrotik = $mikrotik;
        $this->ownerNotifications = $ownerNotifications;
    }

    /**
     * View location portal (Splash Page)
     */
    public function showPortal($slug, Request $request)
    {
        $location = Location::where('slug', $slug)->where('status', 'active')->firstOrFail();
        $packages = $location->packages;

        // The cookie is encrypted and only issued after a verified payment. Do not trust a username in the URL.
        $username = $request->cookie('oyalo_customer_username');
        $customer = null;

        if ($username) {
            $customer = Customer::where('username', $username)->where('location_id', $location->id)->first();
        }

        $announcements = Announcement::visible()
            ->where(fn ($query) => $query->whereNull('location_id')->orWhere('location_id', $location->id))
            ->orderByDesc('priority')->oldest()->get();

        return view('customer.portal', compact('location', 'packages', 'customer', 'announcements'));
    }

    /** Public voucher lookup page. It returns only the status for the voucher's own location. */
    public function showSubscriptionStatus(string $slug, Request $request)
    {
        $location = Location::where('slug', $slug)->where('status', 'active')->firstOrFail();
        $customer = null;

        if ($request->filled('voucher')) {
            $voucher = strtoupper(trim($request->string('voucher')->toString()));
            $customer = Customer::where('location_id', $location->id)->where('voucher_code', $voucher)->first();

            if (!$customer) {
                return back()->withInput()->with('error', 'Voucher not found for this location.');
            }
        }

        return view('customer.subscription-status', compact('location', 'customer'));
    }

    /**
     * Initiate payment via Paystack
     */
    public function checkout(Request $request, $slug)
    {
        $request->validate([
            'phone_number' => 'required|string',
            'package_id' => 'required|exists:packages,id',
            'mac_address' => 'nullable|string', // Optional, to auto-register current TV/Device MAC
            'device_name' => 'nullable|string',
        ]);

        $location = Location::where('slug', $slug)->where('status', 'active')->firstOrFail();
        // Never allow a package from another hotspot to be charged on this portal.
        $package = Package::whereKey($request->package_id)->where('location_id', $location->id)->firstOrFail();

        if (!$location->paystack_subaccount) {
            return back()->with('error', 'Online payment is currently unavailable at this location.');
        }

        // Generate a unique reference
        $reference = 'OY-' . strtoupper(Str::random(10));

        // Create a temporary cookie/session data or database entry for pending transaction
        $email = $request->phone_number . '@oyalo.net'; // Paystack requires email

        // Initialize Split Payment on Paystack
        $paystackData = $this->paystack->initializeTransaction(
            $email,
            $package->price,
            $reference,
            route('customer.payment.callback', ['slug' => $slug]),
            $location->paystack_subaccount
        );

        if (!$paystackData) {
            return back()->with('error', 'Failed to connect to Paystack payment gateway. Please try again.');
        }

        // Record pending payment
        Payment::create([
            'location_id' => $location->id,
            'package_id' => $package->id,
            'amount' => $package->price,
            'paystack_reference' => $reference,
            'status' => 'pending',
            'platform_commission' => $package->price * ($location->commission_percentage / 100),
            'paystack_fee' => $package->price * (config('services.paystack.fee_percentage') / 100),
        ]);

        // Save phone/MAC details in session to link after callback
        session([
            'pending_payment_phone' => $request->phone_number,
            'pending_payment_mac' => $request->mac_address,
            'pending_payment_device_name' => $request->device_name ?: 'My Smart Device',
        ]);

        return redirect($paystackData['authorization_url']);
    }

    /**
     * Paystack Payment Webhook / Return Callback
     */
    public function paymentCallback(Request $request, $slug)
    {
        $location = Location::where('slug', $slug)->firstOrFail();
        $reference = $request->input('reference') ?: $request->input('trxref');

        if (!$reference) {
            return redirect()->route('customer.portal', $slug)->with('error', 'No reference returned.');
        }

        $payment = Payment::where('paystack_reference', $reference)->first();

        if (!$payment) {
            return redirect()->route('customer.portal', $slug)->with('error', 'Payment transaction not found.');
        }

        if ($payment->status === 'success') {
            // Already processed
            $customer = $payment->customer;
            return redirect()->route('customer.success', ['slug' => $slug, 'username' => $customer->username]);
        }

        // Verify with Paystack
        $verification = $this->paystack->verifyTransaction($reference);

        if ($verification && $verification['status'] === 'success') {
            $payment->update(['status' => 'success']);

            $package = $payment->package;
            $phone = session('pending_payment_phone') ?: '0000000000';

            // Check if customer exists already (renewal) or create new
            $customer = Customer::where('phone_number', $phone)
                                ->where('location_id', $location->id)
                                ->first();

            if (!$customer) {
                // One voucher is used as both MikroTik username and password.
                $voucher = $this->uniqueVoucher();

                $customer = Customer::create([
                    'location_id' => $location->id,
                    'username' => $voucher,
                    'password' => $voucher,
                    'voucher_code' => $voucher,
                    'phone_number' => $phone,
                    'active_package_id' => $package->id,
                    'expires_at' => Carbon::now()->addMinutes($package->duration_minutes),
                    'status' => 'active',
                ]);
            } else {
                // Update existing customer package and extend expiry
                $currentExpiry = ($customer->expires_at && $customer->expires_at->isFuture()) 
                    ? $customer->expires_at 
                    : Carbon::now();

                $customer->update([
                    'active_package_id' => $package->id,
                    'expires_at' => $currentExpiry->addMinutes($package->duration_minutes),
                    'status' => 'active',
                ]);
            }

            // Link customer to payment
            $payment->update(['customer_id' => $customer->id]);

            // Queue MikroTik command for user
            $router = $location->routers()->where('status', 'online')->first() 
                      ?: $location->routers()->first();

            if ($router) {
                $this->mikrotik->queueCreateUser($router, $customer);
            }

            // TV / Device MAC Auto-Registration Feature (if provided during checkout)
            $mac = session('pending_payment_mac');
            if ($mac && $router) {
                $cleanMac = strtoupper(str_replace('-', ':', $mac));
                // Add device to customer
                $device = Device::create([
                    'customer_id' => $customer->id,
                    'mac_address' => $cleanMac,
                    'name' => session('pending_payment_device_name') ?: 'TV / Smart Device',
                    'status' => 'active'
                ]);

                // Queue ADD_MAC command
                $this->mikrotik->queueAddMac($router, $device, $customer);
            }

            // Send credentials and optionally notify the location owner by email.
            $this->sms->sendCredentials($customer, $package->name);
            $this->ownerNotifications->subscriptionCreated($location->loadMissing('admin'), $customer, $package, $payment->fresh());
            app(ActivityLogger::class)->record('payment.completed', "Online subscription {$payment->paystack_reference} completed for voucher {$customer->voucher_code} at {$location->name}.", null, $request->ip());

            // Clear pending session data
            session()->forget(['pending_payment_phone', 'pending_payment_mac', 'pending_payment_device_name']);

            // Queue success cookie
            return redirect()->route('customer.success', ['slug' => $slug, 'username' => $customer->username])
                             ->cookie('oyalo_customer_username', $customer->username, 43200); // 30 days
        }

        $payment->update(['status' => 'failed']);
        return redirect()->route('customer.portal', $slug)->with('error', 'Payment verification failed. If you were debited, please contact support.');
    }

    /**
     * Payment Success Page (Shows details and PWA Install Guide)
     */
    public function showSuccess($slug, Request $request)
    {
        $location = Location::where('slug', $slug)->firstOrFail();
        $username = $request->query('username');
        $customer = Customer::where('username', $username)->where('location_id', $location->id)->firstOrFail();

        return view('customer.success', compact('location', 'customer'));
    }

    /**
     * Device registration (Smart TV / Laptop MAC Feature) from Customer Dashboard
     */
    public function registerDevice(Request $request, $slug)
    {
        $request->validate([
            'username' => 'required|exists:customers,username',
            'mac_address' => 'required|string',
            'device_name' => 'required|string|max:50',
        ]);

        $location = Location::where('slug', $slug)->firstOrFail();
        $customer = Customer::where('username', $request->username)
                            ->where('location_id', $location->id)
                            ->firstOrFail();
        abort_unless(hash_equals((string) $customer->username, (string) $request->cookie('oyalo_customer_username')), 403);

        if ($customer->isExpired()) {
            return back()->with('error', 'You must have an active package to register device MAC addresses.');
        }

        // Clean MAC address
        $mac = strtoupper(trim($request->mac_address));
        $mac = str_replace('-', ':', $mac);

        // Basic MAC regex validation
        if (!preg_match('/^([0-9A-F]{2}[:-]){5}([0-9A-F]{2})$/', $mac)) {
            return back()->with('error', 'Invalid MAC address format. Use AA:BB:CC:DD:EE:FF');
        }

        // Check limit: e.g. Max 3 devices per account
        if ($customer->devices()->count() >= 3) {
            return back()->with('error', 'You have reached the limit of 3 registered devices.');
        }

        // Create Device
        $device = Device::create([
            'customer_id' => $customer->id,
            'mac_address' => $mac,
            'name' => $request->device_name,
            'status' => 'active'
        ]);

        // Push command to router
        $router = $location->routers()->first();
        if ($router) {
            $this->mikrotik->queueAddMac($router, $device, $customer);
        }

        return back()->with('success', "Device {$request->device_name} registered successfully. It will connect to the internet shortly.");
    }

    /**
     * Delete/Remove Device MAC Address
     */
    public function removeDevice($slug, $deviceId)
    {
        $location = Location::where('slug', $slug)->firstOrFail();
        $device = Device::findOrFail($deviceId);
        $customer = $device->customer;

        if ($customer->location_id !== $location->id || !hash_equals((string) $customer->username, (string) $request->cookie('oyalo_customer_username'))) {
            abort(403);
        }

        // Push REMOVE_MAC to router
        $router = $location->routers()->first();
        if ($router) {
            $this->mikrotik->queueRemoveMac($router, $device);
        }

        $device->delete();

        return back()->with('success', 'Device removed successfully. Router will sync the removal.');
    }

    private function uniqueVoucher(): string
    {
        do {
            $voucher = 'OY-'.strtoupper(Str::random(8));
        } while (Customer::where('voucher_code', $voucher)->exists());

        return $voucher;
    }
}
