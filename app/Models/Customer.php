<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'location_id',
        'username',
        'password',
        'voucher_code',
        'phone_number',
        'active_package_id',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function activePackage()
    {
        return $this->belongsTo(Package::class, 'active_package_id');
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function activeDevices()
    {
        return $this->hasMany(Device::class)->where('status', 'active');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function smsLogs()
    {
        return $this->hasMany(SmsLog::class);
    }

    public function isExpired(): bool
    {
        return !$this->expires_at || $this->expires_at->isPast();
    }

    public function hasActiveAccess(): bool
    {
        return $this->status === 'active' && !$this->isExpired();
    }
}
