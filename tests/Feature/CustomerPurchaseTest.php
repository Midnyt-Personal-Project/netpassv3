<?php

use App\Jobs\SendOwnerSubscriptionEmail;
use App\Jobs\SendSubscriptionCredentialsSms;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Router;
use App\Models\RouterCommand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function purchaseFixture(): array
{
    $admin = User::factory()->create(['role' => 'admin']);
    $location = Location::create([
        'admin_id' => $admin->id,
        'name' => 'Purchase Test Hotspot',
        'slug' => 'purchase-test',
        'paystack_subaccount' => 'ACCT_test',
    ]);
    $package = Package::create([
        'location_id' => $location->id,
        'name' => 'One hour',
        'price' => 5,
        'duration_minutes' => 60,
    ]);
    $router = Router::create([
        'location_id' => $location->id,
        'router_id' => 'RTR-PURCHASE-TEST',
        'api_token' => 'router-test-token',
        'name' => 'Purchase test router',
    ]);

    return [$location, $package, $router];
}

function fakeSuccessfulPaystack(): void
{
    config(['services.paystack.secret_key' => 'sk_test_example']);

    Http::fake(function (ClientRequest $request) {
        if (str_ends_with($request->url(), '/transaction/initialize')) {
            return Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test-access',
                    'access_code' => 'test-access',
                ],
            ]);
        }

        if (str_contains($request->url(), '/transaction/verify/')) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            $reference = urldecode(basename($path));

            return Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => $reference,
                    'amount' => 500,
                    'currency' => 'GHS',
                ],
            ]);
        }

        // SMS gateway request.
        return Http::response(['status' => 'ok']);
    });
}

it('issues a different voucher for every successful purchase by the same phone number', function () {
    Queue::fake();
    [$location, $package] = purchaseFixture();
    fakeSuccessfulPaystack();

    $checkout = fn () => $this->post(route('customer.checkout', $location->slug), [
        'phone_number' => '024 412 3456',
        'package_id' => $package->id,
    ]);

    $checkout()->assertRedirect('https://checkout.paystack.com/test-access');
    $firstPayment = Payment::orderByDesc('id')->firstOrFail();
    $this->get(route('customer.payment.callback', [
        'slug' => $location->slug,
        'reference' => $firstPayment->paystack_reference,
    ]))->assertRedirect();

    $checkout()->assertRedirect('https://checkout.paystack.com/test-access');
    $secondPayment = Payment::orderByDesc('id')->firstOrFail();
    $this->get(route('customer.payment.callback', [
        'slug' => $location->slug,
        'reference' => $secondPayment->paystack_reference,
    ]))->assertRedirect();

    $customers = Customer::orderBy('id')->get();

    expect($customers)->toHaveCount(2)
        ->and($customers->pluck('phone_number')->unique()->all())->toBe(['233244123456'])
        ->and($customers[0]->voucher_code)->not->toBe($customers[1]->voucher_code)
        ->and($firstPayment->fresh()->customer_id)->not->toBe($secondPayment->fresh()->customer_id)
        ->and($firstPayment->fresh()->processed_at)->not->toBeNull()
        ->and($secondPayment->fresh()->processed_at)->not->toBeNull()
        ->and(RouterCommand::where('command_type', 'CREATE_USER')->count())->toBe(2);

    // Replaying one successful callback must not issue another voucher.
    $this->get(route('customer.payment.callback', [
        'slug' => $location->slug,
        'reference' => $firstPayment->paystack_reference,
    ]))->assertRedirect();

    expect(Customer::count())->toBe(2)
        ->and(RouterCommand::where('command_type', 'CREATE_USER')->count())->toBe(2);

    Queue::assertPushed(SendSubscriptionCredentialsSms::class, 2);
    Queue::assertPushed(SendOwnerSubscriptionEmail::class, 2);
});

it('fulfills a signed Paystack webhook once even when it is replayed', function () {
    Queue::fake();
    [$location, $package] = purchaseFixture();
    fakeSuccessfulPaystack();

    $this->post(route('customer.checkout', $location->slug), [
        'phone_number' => '0244123456',
        'package_id' => $package->id,
    ])->assertRedirect();

    $payment = Payment::firstOrFail();
    $payload = json_encode([
        'event' => 'charge.success',
        'data' => [
            'status' => 'success',
            'reference' => $payment->paystack_reference,
            'amount' => 500,
            'currency' => 'GHS',
        ],
    ], JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha512', $payload, 'sk_test_example');

    $webhook = fn () => $this->call(
        'POST',
        '/api/paystack/webhook',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $signature],
        $payload,
    );

    $webhook()->assertOk()->assertJson(['status' => 'success', 'newly_processed' => true]);
    $webhook()->assertOk()->assertJson(['status' => 'success', 'newly_processed' => false]);

    expect(Customer::count())->toBe(1)
        ->and($payment->fresh()->status)->toBe('success')
        ->and(RouterCommand::where('command_type', 'CREATE_USER')->count())->toBe(1);

    Queue::assertPushed(SendSubscriptionCredentialsSms::class, 1);
    Queue::assertPushed(SendOwnerSubscriptionEmail::class, 1);
});

it('rejects a Paystack webhook with an invalid signature', function () {
    config(['services.paystack.secret_key' => 'sk_test_example']);

    $this->withHeader('x-paystack-signature', 'not-valid')
        ->postJson('/api/paystack/webhook', ['event' => 'charge.success'])
        ->assertUnauthorized();
});

it('also issues a fresh voucher for repeated manual sales to the same phone', function () {
    Queue::fake();
    [$location, $package] = purchaseFixture();
    Http::fake(fn () => Http::response(['status' => 'ok']));

    $sale = fn () => $this->actingAs($location->admin)->post(route('admin.subscriptions.create'), [
        'location_id' => $location->id,
        'package_id' => $package->id,
        'phone_number' => '0244123456',
    ]);

    $sale()->assertSessionHas('success');
    $sale()->assertSessionHas('success');

    expect(Customer::count())->toBe(2)
        ->and(Customer::pluck('voucher_code')->unique()->count())->toBe(2)
        ->and(Payment::where('status', 'success')->count())->toBe(2);

    Queue::assertPushed(SendSubscriptionCredentialsSms::class, 2);
    Queue::assertPushed(SendOwnerSubscriptionEmail::class, 2);
});

it('does not fulfill a callback through a different location', function () {
    [$location, $package] = purchaseFixture();
    fakeSuccessfulPaystack();

    $this->post(route('customer.checkout', $location->slug), [
        'phone_number' => '0244123456',
        'package_id' => $package->id,
    ])->assertRedirect();

    $otherAdmin = User::factory()->create(['role' => 'admin']);
    $otherLocation = Location::create([
        'admin_id' => $otherAdmin->id,
        'name' => 'Other Hotspot',
        'slug' => 'other-hotspot',
    ]);
    $payment = Payment::firstOrFail();

    $this->get(route('customer.payment.callback', [
        'slug' => $otherLocation->slug,
        'reference' => $payment->paystack_reference,
    ]))->assertSessionHas('error');

    expect(Customer::count())->toBe(0)
        ->and($payment->fresh()->status)->toBe('pending');
});
