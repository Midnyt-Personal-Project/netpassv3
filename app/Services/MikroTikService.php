<?php

namespace App\Services;

use App\Models\Router;
use App\Models\RouterCommand;
use App\Models\Customer;
use App\Models\Device;

class MikroTikService
{
    /**
     * Queue user creation command.
     */
    public function queueCreateUser(Router $router, Customer $customer)
    {
        $package = $customer->activePackage;
        $profile = $package ? "oyalo_{$package->id}" : "default";

        // Speed limit syntax in MikroTik e.g., "5M/2M" (download/upload)
        $rateLimit = null;
        if ($package && $package->speed_limit_down && $package->speed_limit_up) {
            $rateLimit = "{$package->speed_limit_up}/{$package->speed_limit_down}";
        }

        return RouterCommand::create([
            'router_id' => $router->id,
            'command_type' => 'CREATE_USER',
            'payload' => [
                'username' => $customer->username,
                'password' => $customer->password,
                'profile' => $profile,
                'rate_limit' => $rateLimit,
                'expires_at' => $customer->expires_at ? $customer->expires_at->toIso8601String() : null,
            ],
            'status' => 'pending'
        ]);
    }

    /**
     * Queue user deletion command.
     */
    public function queueRemoveUser(Router $router, Customer $customer)
    {
        return RouterCommand::create([
            'router_id' => $router->id,
            'command_type' => 'REMOVE_USER',
            'payload' => [
                'username' => $customer->username,
            ],
            'status' => 'pending'
        ]);
    }

    /**
     * Queue user disabling.
     */
    public function queueDisableUser(Router $router, Customer $customer)
    {
        return RouterCommand::create([
            'router_id' => $router->id,
            'command_type' => 'DISABLE_USER',
            'payload' => [
                'username' => $customer->username,
            ],
            'status' => 'pending'
        ]);
    }

    /**
     * Queue MAC registration (TV/Smart devices).
     */
    public function queueAddMac(Router $router, Device $device, Customer $customer)
    {
        $package = $customer->activePackage;
        $rateLimit = null;
        if ($package && $package->speed_limit_down && $package->speed_limit_up) {
            $rateLimit = "{$package->speed_limit_up}/{$package->speed_limit_down}";
        }

        return RouterCommand::create([
            'router_id' => $router->id,
            'command_type' => 'ADD_MAC',
            'payload' => [
                'mac' => $device->mac_address,
                'username' => $customer->username, // Associated customer for identification
                'rate_limit' => $rateLimit,
                'comment' => "Oyalo: {$device->name} ({$customer->username})"
            ],
            'status' => 'pending'
        ]);
    }

    /** Restore active device MAC access after a voucher is created or renewed. */
    public function queueActiveDevices(Router $router, Customer $customer): void
    {
        $customer->loadMissing('activePackage', 'devices');

        foreach ($customer->devices->where('status', 'active') as $device) {
            $this->queueAddMac($router, $device, $customer);
        }
    }

    /**
     * Queue MAC removal.
     */
    public function queueRemoveMac(Router $router, Device $device)
    {
        return RouterCommand::create([
            'router_id' => $router->id,
            'command_type' => 'REMOVE_MAC',
            'payload' => [
                'mac' => $device->mac_address,
            ],
            'status' => 'pending'
        ]);
    }
}
