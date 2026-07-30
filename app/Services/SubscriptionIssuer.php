<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Device;
use App\Models\Location;
use App\Models\Package;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SubscriptionIssuer
{
    public function __construct(private readonly MikroTikService $mikrotik)
    {
    }

    /**
     * Issue one independent voucher. A phone number identifies the purchaser,
     * not the voucher, so the same number can purchase any number of vouchers.
     */
    public function issue(
        Location $location,
        Package $package,
        string $phoneNumber,
        ?string $macAddress = null,
        ?string $deviceName = null,
    ): Customer
    {
        if ((int) $package->location_id !== (int) $location->id) {
            throw new InvalidArgumentException('The package does not belong to this location.');
        }

        $voucher = $this->uniqueVoucher();
        $customer = Customer::create([
            'location_id' => $location->id,
            'username' => $voucher,
            'password' => $voucher,
            'voucher_code' => $voucher,
            'phone_number' => $phoneNumber,
            'active_package_id' => $package->id,
            'expires_at' => now()->addMinutes($package->duration_minutes),
            'status' => 'active',
        ]);

        $device = $macAddress
            ? $this->assignDevice($location, $customer, $macAddress, $deviceName)
            : null;

        // A location may have more than one access router. The voucher must be
        // available on all of them rather than only whichever router is first.
        foreach ($location->routers()->get() as $router) {
            $this->mikrotik->queueCreateUser($router, $customer);

            if ($device) {
                $this->mikrotik->queueAddMac($router, $device, $customer);
            }
        }

        return $customer->load(['activePackage', 'devices']);
    }

    private function assignDevice(
        Location $location,
        Customer $customer,
        string $macAddress,
        ?string $deviceName,
    ): Device
    {
        // Transfer a MAC already used at this hotspot to the newly purchased
        // voucher. Leaving it on the old voucher would let the old expiry job
        // remove access from the newer purchase.
        $device = Device::where('mac_address', $macAddress)
            ->whereHas('customer', fn ($query) => $query->where('location_id', $location->id))
            ->first();

        if ($device) {
            $device->update([
                'customer_id' => $customer->id,
                'name' => $deviceName ?: $device->name,
                'status' => 'active',
            ]);

            return $device;
        }

        return Device::create([
            'customer_id' => $customer->id,
            'mac_address' => $macAddress,
            'name' => $deviceName ?: 'TV / Smart Device',
            'status' => 'active',
        ]);
    }

    private function uniqueVoucher(): string
    {
        do {
            // Ten random characters provide far more room than the old
            // eight-character code while remaining easy to type.
            $voucher = 'OY-'.Str::upper(Str::random(10));
        } while (Customer::where('voucher_code', $voucher)->exists());

        return $voucher;
    }
}
