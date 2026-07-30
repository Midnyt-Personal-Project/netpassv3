<?php

use App\Jobs\SendExpiryNotificationSms;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Location;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Router;
use App\Models\RouterCommand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function maintenanceLocation(): array
{
    $admin = User::factory()->create(['role' => 'admin']);
    $location = Location::create([
        'admin_id' => $admin->id,
        'name' => 'Maintenance Test Hotspot',
        'slug' => 'maintenance-test',
    ]);
    $package = Package::create([
        'location_id' => $location->id,
        'name' => 'Expired plan',
        'price' => 5,
        'duration_minutes' => 60,
    ]);
    $router = Router::create([
        'location_id' => $location->id,
        'router_id' => 'RTR-MAINTENANCE-TEST',
        'api_token' => 'maintenance-router-token',
        'name' => 'Maintenance router',
        'status' => 'online',
        'last_heartbeat' => now(),
    ]);

    return [$admin, $location, $package, $router];
}

it('expires overdue vouchers and queues user and device removal on every router', function () {
    Queue::fake();
    [$admin, $location, $package, $router] = maintenanceLocation();
    $secondRouter = Router::create([
        'location_id' => $location->id,
        'router_id' => 'RTR-MAINTENANCE-SECOND',
        'api_token' => 'maintenance-router-token-second',
        'name' => 'Second maintenance router',
    ]);
    $customer = Customer::create([
        'location_id' => $location->id,
        'username' => 'OY-EXPIRED01',
        'password' => 'OY-EXPIRED01',
        'voucher_code' => 'OY-EXPIRED01',
        'phone_number' => '233244123456',
        'active_package_id' => $package->id,
        'expires_at' => now()->subSecond(),
        'status' => 'active',
    ]);
    Device::create([
        'customer_id' => $customer->id,
        'mac_address' => 'AA:BB:CC:DD:EE:FF',
        'name' => 'Expired device',
        'status' => 'active',
    ]);
    Payment::create([
        'location_id' => $location->id,
        'customer_id' => $customer->id,
        'package_id' => $package->id,
        'amount' => 5,
        'paystack_reference' => 'MANUAL-EXPIRED-TEST',
        'status' => 'success',
    ]);

    $this->artisan('subscriptions:expire')->assertSuccessful();

    expect($customer->fresh()->status)->toBe('expired')
        ->and(RouterCommand::where('command_type', 'REMOVE_USER')->count())->toBe(2)
        ->and(RouterCommand::where('command_type', 'REMOVE_MAC')->count())->toBe(2)
        ->and(RouterCommand::where('router_id', $router->id)->where('payload->username', $customer->username)->exists())->toBeTrue()
        ->and(RouterCommand::where('router_id', $secondRouter->id)->where('payload->username', $customer->username)->exists())->toBeTrue();

    Queue::assertPushed(SendExpiryNotificationSms::class, fn ($job) => $job->customerId === $customer->id);

    $this->actingAs($admin)
        ->get(route('admin.subscriptions'))
        ->assertOk()
        ->assertSee('Expired');

    // Re-running the command must not duplicate router commands or SMS jobs.
    $this->artisan('subscriptions:expire')->assertSuccessful();
    expect(RouterCommand::count())->toBe(4);
    Queue::assertPushed(SendExpiryNotificationSms::class, 1);
});

it('marks only routers with stale heartbeats offline', function () {
    [, $location, , $staleRouter] = maintenanceLocation();
    $staleRouter->update(['last_heartbeat' => now()->subMinutes(4)]);
    $freshRouter = Router::create([
        'location_id' => $location->id,
        'router_id' => 'RTR-FRESH-TEST',
        'api_token' => 'fresh-router-token',
        'name' => 'Fresh router',
        'status' => 'online',
        'last_heartbeat' => now()->subMinute(),
    ]);

    $this->artisan('routers:mark-offline', ['--minutes' => 3])->assertSuccessful();

    expect($staleRouter->fresh()->status)->toBe('offline')
        ->and($freshRouter->fresh()->status)->toBe('online')
        ->and(ActivityLog::where('action', 'router.offline')->count())->toBe(1);

    $this->artisan('routers:mark-offline', ['--minutes' => 3])->assertSuccessful();
    expect(ActivityLog::where('action', 'router.offline')->count())->toBe(1);
});
