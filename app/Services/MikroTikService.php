<?php

namespace App\Services;

use App\Models\{Customer, Device, Package, Router, RouterCommand};

class MikroTikService
{
    /** Queue a hotspot profile on a router. */
    public function queueCreateProfile(Router $router, Package $package): RouterCommand
    {
        return RouterCommand::create([
            'router_id' => $router->id,
            'command_type' => 'CREATE_PROFILE',
            'payload' => [
                'name' => $this->profileName($package),
                'speed_down' => $package->speed_limit_down ?: '0',
                'speed_up' => $package->speed_limit_up ?: '0',
                'duration_minutes' => $package->duration_minutes,
                'duration_formatted' => sprintf(
                    '%dd %02d:%02d:%02d',
                    floor($package->duration_minutes / 1440),
                    floor(($package->duration_minutes % 1440) / 60),
                    $package->duration_minutes % 60,
                    0,
                ),
                'share_users' => $package->share_users ?? 1,
            ],
            'status' => 'pending',
        ]);
    }

    /**
     * Queue user creation command.
     */
    public function queueCreateUser(Router $router, Customer $customer): RouterCommand
    {
        $package = $customer->activePackage;
        // Friendly, unique profile names: oyalo-2days-12, oyalo-1hour-8, etc.
        $profile = $package ? $this->profileName($package) : 'default';

        // MikroTik rate-limit syntax is download/upload. A missing direction is unlimited (0).
        $rateLimit = null;
        if ($package && ($package->speed_limit_down || $package->speed_limit_up)) {
            $rateLimit = ($package->speed_limit_down ?: '0').'/'.($package->speed_limit_up ?: '0');
        }

        return RouterCommand::create([
            'router_id' => $router->id,
            'command_type' => 'CREATE_USER',
            'payload' => [
                'username' => $customer->username,
                'password' => $customer->password,
                'profile' => $profile,
                'rate_limit' => $rateLimit,
                'duration_minutes' => $package?->duration_minutes,
                'expires_at' => $customer->expires_at ? $customer->expires_at->toIso8601String() : null,
            ],
            'status' => 'pending'
        ]);
    }

    /**
     * Queue user deletion command.
     */
    public function queueRemoveUser(Router $router, Customer $customer): RouterCommand
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
    public function queueDisableUser(Router $router, Customer $customer): RouterCommand
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
    public function queueAddMac(Router $router, Device $device, Customer $customer): RouterCommand
    {
        $package = $customer->activePackage;

        return RouterCommand::create([
            'router_id' => $router->id,
            'command_type' => 'ADD_MAC',
            'payload' => [
                'mac' => $device->mac_address,
                'profile' => $package ? $this->profileName($package) : 'default',
                'username' => $customer->username,
            ],
            'status' => 'pending'
        ]);
    }

    /** Build a human-readable but collision-safe profile name from the package duration. */
    public function profileName(Package $package): string
    {
        $minutes = $package->duration_minutes;
        if ($minutes % 43200 === 0) {
            $label = ($minutes / 43200).'month'.($minutes === 43200 ? '' : 's');
        } elseif ($minutes % 1440 === 0) {
            $label = ($minutes / 1440).'day'.($minutes === 1440 ? '' : 's');
        } elseif ($minutes % 60 === 0) {
            $label = ($minutes / 60).'hour'.($minutes === 60 ? '' : 's');
        } else {
            $label = $minutes.'minutes';
        }

        return "oyalo-{$label}-{$package->id}";
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
    public function queueRemoveMac(Router $router, Device $device): RouterCommand
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
