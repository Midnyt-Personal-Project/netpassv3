<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'location_id',
        'customer_id',
        'purchaser_phone',
        'requested_mac_address',
        'requested_device_name',
        'package_id',
        'amount',
        'currency',
        'paystack_reference',
        'status',
        'processed_at',
        'platform_commission',
        'paystack_fee',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'platform_commission' => 'decimal:2',
        'paystack_fee' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
