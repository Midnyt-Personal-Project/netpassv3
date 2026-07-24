<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = [
        'customer_id',
        'mac_address',
        'name',
        'status',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
