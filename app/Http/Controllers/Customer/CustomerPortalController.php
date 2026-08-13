<?php

namespace App\Http\Controllers\Customer;

use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

use App\Http\Controllers\Controller;
use App\Models\{Announcement, Customer, Device, Location, Package, Payment};
use App\Services\{MikroTikService, PaymentFulfillmentService, PaystackService};
use App\Support\PhoneNumber;

class CustomerPortalController extends Controller
{
    public function __construct(
        private readonly PaystackService $paystack,
        private readonly MikroTikService $mikrotik,
        private readonly PaymentFulfillmentService $paymentFulfillment,
    ) {}

    /** View a location's public hotspot portal. */
    public function showPortal(string $slug, Request $request)
    {
        $location = Location::where('slug', $slug)->where('status', 'active')->firstOrFail();
        $packages = $location->packages()->orderBy('price')->get();

        // This encrypted cookie is issued only after a verified payment. A
        // phone number is never used to select which voucher is displayed.
        $username = $request->cookie('oyalo_customer_username');
        $customer = $username
            ? Customer::with(['activePackage', 'devices'])
            ->where('username', $username)
            ->where('location_id', $location->id)
            ->first()
            : null;

        $announcements = Announcement::visible()
            ->where(fn($query) => $query->whereNull('location_id')->orWhere('location_id', $location->id))
            ->orderByDesc('priority')
            ->oldest()
            ->get();

        return view('customer.portal', compact('location', 'packages', 'customer', 'announcements'));
    }

    /** Public voucher lookup page scoped to the current location. */
    public function showSubscriptionStatus(string $slug, Request $request)
    {
        $location = Location::where('slug', $slug)->where('status', 'active')->firstOrFail();
        $customer = null;

        if ($request->filled('voucher')) {
            $voucher = Str::upper(trim($request->string('voucher')->toString()));
            $customer = Customer::where('location_id', $location->id)
                ->where('voucher_code', $voucher)
                ->first();

            if (!$customer) {
                return back()->withInput()->with('error', 'Voucher not found for this location.');
            }
        }

        return view('customer.subscription-status', compact('location', 'customer'));
    }

    /** Initialize a Paystack checkout and persist everything needed by its callback. */
    public function checkout(Request $request, string $slug): RedirectResponse
    {
        $phone = trim((string) $request->input('phone_number'));

        // Convert 233XXXXXXXXX to 0XXXXXXXXX if somebody enters international format
        if (preg_match('/^233([0-9]{9})$/', $phone, $matches)) {
            $phone = '0' . $matches[1];
        }

        $request->merge([
            'phone_number' => $phone,
        ]);

        $data = $request->validate([
            'phone_number' => [
                'required',
                'string',
                'regex:/^0[2-9][0-9]{8}$/',
            ],
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'mac_address' => [
                'nullable',
                'required_with:device_name',
                'string',
                'regex:/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/',
            ],
            'device_name' => [
                'nullable',
                'required_with:mac_address',
                'string',
                'max:50',
            ],
        ], [
            'phone_number.regex' => 'Enter a valid Ghana phone number, for example 0244123456.',
        ]);

        $location = Location::where('slug', $slug)->where('status', 'active')->firstOrFail();
        $package = Package::whereKey($data['package_id'])
            ->where('location_id', $location->id)
            ->firstOrFail();

        if (!$location->paystack_subaccount) {
            return back()->withInput()->with('error', 'Online payment is currently unavailable at this location.');
        }

        $reference = $this->uniquePaymentReference();
        $macAddress = isset($data['mac_address'])
            ? Str::upper(str_replace('-', ':', $data['mac_address']))
            : null;

        // Store callback context before contacting Paystack. Browser sessions
        // are not reliable identifiers for payment callbacks or parallel tabs.
        $payment = Payment::create([
            'location_id' => $location->id,
            'purchaser_phone' => $data['phone_number'],
            'requested_mac_address' => $macAddress,
            'requested_device_name' => $data['device_name'] ?? null,
            'package_id' => $package->id,
            'amount' => $package->price,
            'currency' => 'GHS',
            'paystack_reference' => $reference,
            'status' => 'pending',
            'platform_commission' => $package->price * ($location->commission_percentage / 100),
            'paystack_fee' => $package->price * (config('services.paystack.fee_percentage') / 100),
        ]);

        $paystackData = $this->paystack->initializeTransaction(
            $data['phone_number'] . '@oyalo.net',
            (float) $package->price,
            $reference,
            route('customer.payment.callback', ['slug' => $slug]),
            $location->paystack_subaccount,
            [
                'payment_id' => $payment->id,
                'location_id' => $location->id,
                'package_id' => $package->id,
            ],
        );

        if (!$paystackData || empty($paystackData['authorization_url'])) {
            $payment->update(['status' => 'failed']);

            return back()->withInput()->with('error', 'Failed to connect to Paystack. Please try again.');
        }

        return redirect()->away($paystackData['authorization_url']);
    }

    /** Verify and fulfill a Paystack return callback exactly once. */
    public function paymentCallback(Request $request, string $slug): RedirectResponse
    {
        $location = Location::where('slug', $slug)->firstOrFail();
        $reference = trim((string) ($request->input('reference') ?: $request->input('trxref')));

        if ($reference === '') {
            return redirect()->route('customer.portal', $slug)->with('error', 'No payment reference was returned.');
        }

        // Scoping by location prevents a valid reference from being processed
        // through another hotspot's callback URL.
        $payment = Payment::with(['customer', 'package'])
            ->where('location_id', $location->id)
            ->where('paystack_reference', $reference)
            ->first();

        if (!$payment) {
            return redirect()->route('customer.portal', $slug)->with('error', 'Payment transaction not found.');
        }

        if ($payment->status === 'success' && $payment->customer) {
            return $this->successResponse($location, $payment);
        }

        $verification = $this->paystack->verifyTransaction($reference);

        if (!$verification || !$this->paystack->transactionMatchesPayment($verification, $payment)) {
            if (is_array($verification) && ($verification['status'] ?? null) !== 'success') {
                $payment->update(['status' => 'failed']);
            }

            Log::warning('Paystack callback verification did not match the pending payment.', [
                'payment_id' => $payment->id,
                'reference' => $reference,
                'verification_status' => is_array($verification) ? ($verification['status'] ?? null) : null,
            ]);

            return redirect()->route('customer.portal', $slug)
                ->with('error', 'Payment could not be verified. If you were debited, contact support with your reference.');
        }

        try {
            $result = $this->paymentFulfillment->fulfill(
                $payment,
                session('pending_payment_phone'), // Legacy pre-migration checkout fallback.
                $request->ip(),
            );
            $payment = $result['payment'];
        } catch (Throwable $exception) {
            Log::error('Verified Paystack payment could not be fulfilled.', [
                'payment_id' => $payment->id,
                'reference' => $reference,
                'error' => $exception->getMessage(),
            ]);

            return redirect()->route('customer.portal', $slug)
                ->with('error', 'Your payment is verified but access is still being activated. Contact support with your reference.');
        }

        return $this->successResponse($location, $payment);
    }

    /** Show only the voucher linked to the verified payment and encrypted cookie. */
    public function showSuccess(string $slug, Request $request)
    {
        $location = Location::where('slug', $slug)->firstOrFail();
        $payment = Payment::with('customer')
            ->where('location_id', $location->id)
            ->where('paystack_reference', $request->query('reference'))
            ->where('status', 'success')
            ->whereNotNull('customer_id')
            ->firstOrFail();

        $customer = $payment->customer;
        abort_unless(
            hash_equals((string) $customer->username, (string) $request->cookie('oyalo_customer_username')),
            403,
        );

        return view('customer.success', compact('location', 'customer', 'payment'));
    }

    /** Register a smart-device MAC against the voucher held in the cookie. */
    public function registerDevice(Request $request, string $slug): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'exists:customers,username'],
            'mac_address' => ['required', 'string', 'regex:/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/'],
            'device_name' => ['required', 'string', 'max:50'],
        ]);

        $location = Location::where('slug', $slug)->where('status', 'active')->firstOrFail();
        $customer = Customer::where('username', $data['username'])
            ->where('location_id', $location->id)
            ->firstOrFail();

        abort_unless(
            hash_equals((string) $customer->username, (string) $request->cookie('oyalo_customer_username')),
            403,
        );

        if (!$customer->hasActiveAccess()) {
            return back()->with('error', 'You must have an active package to register devices.');
        }

        if ($customer->devices()->count() >= 3) {
            return back()->with('error', 'You have reached the limit of 3 registered devices.');
        }

        $macAddress = Str::upper(str_replace('-', ':', $data['mac_address']));
        $alreadyRegistered = Device::where('mac_address', $macAddress)
            ->whereHas('customer', fn($query) => $query->where('location_id', $location->id))
            ->exists();

        if ($alreadyRegistered) {
            return back()->withInput()->with('error', 'This MAC address is already registered at this location.');
        }

        $device = Device::create([
            'customer_id' => $customer->id,
            'mac_address' => $macAddress,
            'name' => $data['device_name'],
            'status' => 'active',
        ]);

        foreach ($location->routers()->get() as $router) {
            $this->mikrotik->queueAddMac($router, $device, $customer);
        }

        return back()->with('success', "Device {$device->name} registered successfully. It will connect shortly.");
    }

    /** Remove a smart-device MAC owned by the voucher held in the cookie. */
    public function removeDevice(Request $request, string $slug, int $id): RedirectResponse
    {
        $location = Location::where('slug', $slug)->firstOrFail();
        $device = Device::with('customer')->findOrFail($id);
        $customer = $device->customer;

        abort_unless(
            (int) $customer->location_id === (int) $location->id
                && hash_equals((string) $customer->username, (string) $request->cookie('oyalo_customer_username')),
            403,
        );

        foreach ($location->routers()->get() as $router) {
            $this->mikrotik->queueRemoveMac($router, $device);
        }

        $device->delete();

        return back()->with('success', 'Device removed successfully. Router removal was queued.');
    }

    private function successResponse(Location $location, Payment $payment): RedirectResponse
    {
        $customer = $payment->customer;

        return redirect()->route('customer.success', [
            'slug' => $location->slug,
            'reference' => $payment->paystack_reference,
        ])->cookie('oyalo_customer_username', $customer->username, 43200);
    }

    private function uniquePaymentReference(): string
    {
        do {
            $reference = 'OY-' . Str::upper(Str::random(16));
        } while (Payment::where('paystack_reference', $reference)->exists());

        return $reference;
    }
}
