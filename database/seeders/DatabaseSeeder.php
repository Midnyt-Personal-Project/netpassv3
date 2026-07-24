<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Location;
use App\Models\Router;
use App\Models\Package;
use App\Models\Customer;
use App\Models\Device;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run()
    {
        // 1. Create Super Admin
        $superAdmin = User::create([
            'name' => 'Oyalo Super Admin',
            'email' => 'superadmin@oyalo.net',
            'password' => Hash::make('superadmin123'),
            'role' => 'super_admin',
            'status' => 'active'
        ]);

        // 2. Create Admin (John WiFi)
        $admin = User::create([
            'name' => 'John WiFi',
            'email' => 'john@wifi.com',
            'password' => Hash::make('john1234'),
            'role' => 'admin',
            'sms_balance' => 150.00,
            'status' => 'active'
        ]);

        // 3. Create East Legon Location
        $location = Location::create([
            'admin_id' => $admin->id,
            'name' => 'East Legon WiFi',
            'slug' => 'east-legon',
            'paystack_subaccount' => 'ACCT_demo_sub_12345', // Mock subaccount
            'commission_percentage' => 10.00, // 10% commission goes to platform
            'status' => 'active'
        ]);

        // 4. Create Demo Location
        $demoLocation = Location::create([
            'admin_id' => $admin->id,
            'name' => 'Oyalo Demo Spot',
            'slug' => 'demo',
            'paystack_subaccount' => 'ACCT_demo_sub_abcde',
            'commission_percentage' => 8.50,
            'status' => 'active'
        ]);

        // 5. Create Router for East Legon
        $router = Router::create([
            'location_id' => $location->id,
            'router_id' => 'RTR-000001',
            'api_token' => 'oyalo_demo_token_east_legon_xyz',
            'name' => 'RB750 East Legon',
            'model' => 'hEX lite',
            'status' => 'online',
            'last_heartbeat' => now(),
        ]);

        // 6. Create Packages for East Legon
        $p1 = Package::create([
            'location_id' => $location->id,
            'name' => '1 Hour Turbo',
            'price' => 5.00,
            'duration_minutes' => 60,
            'speed_limit_up' => '2M',
            'speed_limit_down' => '5M',
        ]);

        $p2 = Package::create([
            'location_id' => $location->id,
            'name' => '1 Day Unlimited',
            'price' => 10.00,
            'duration_minutes' => 1440,
            'speed_limit_up' => '4M',
            'speed_limit_down' => '10M',
        ]);

        $p3 = Package::create([
            'location_id' => $location->id,
            'name' => '30 Days Premium',
            'price' => 100.00,
            'duration_minutes' => 43200,
            'speed_limit_up' => '10M',
            'speed_limit_down' => '25M',
        ]);

        // Create same packages for Demo Spot
        Package::create([
            'location_id' => $demoLocation->id,
            'name' => '1 Hour Turbo',
            'price' => 5.00,
            'duration_minutes' => 60,
            'speed_limit_up' => '2M',
            'speed_limit_down' => '5M',
        ]);
        
        Package::create([
            'location_id' => $demoLocation->id,
            'name' => '1 Day Unlimited',
            'price' => 10.00,
            'duration_minutes' => 1440,
            'speed_limit_up' => '4M',
            'speed_limit_down' => '10M',
        ]);

        // 7. Create Customer with Device
        $customer = Customer::create([
            'location_id' => $location->id,
            'username' => 'OY100234',
            'password' => '7890',
            'phone_number' => '0244123456',
            'active_package_id' => $p2->id,
            'expires_at' => now()->addDays(1),
            'status' => 'active',
        ]);

        // Create Smart TV Device for Customer
        Device::create([
            'customer_id' => $customer->id,
            'mac_address' => 'AA:BB:CC:11:22:33',
            'name' => 'Samsung 55 Smart TV',
            'status' => 'active',
        ]);

        Device::create([
            'customer_id' => $customer->id,
            'mac_address' => '44:55:66:77:88:99',
            'name' => 'My iPhone 14',
            'status' => 'active',
        ]);
    }
}
