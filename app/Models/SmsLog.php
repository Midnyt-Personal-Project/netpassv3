<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    public const TYPE_VOUCHER = 'voucher';
    public const TYPE_EXPIRY = 'expiry';
    public const TYPE_ANNOUNCEMENT = 'announcement';
    public const TYPE_TEST = 'test';
    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'customer_id',
        'announcement_id',
        'phone_number',
        'message',
        'status',
        'type',
        'attempts',
        'error_message',
        'gateway_response',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }
}
