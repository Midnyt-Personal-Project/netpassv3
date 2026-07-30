<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = [
        'location_id',
        'name',
        'price',
        'duration_minutes',
        'speed_limit_up',
        'speed_limit_down',
        'data_limit_mb',
        'share_users',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_minutes' => 'integer',
        'data_limit_mb' => 'integer',
        'share_users' => 'integer',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class, 'active_package_id');
    }
}
