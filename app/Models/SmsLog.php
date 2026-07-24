<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $fillable = [
        'customer_id',
        'phone_number',
        'message',
        'status',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
