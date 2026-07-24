<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $fillable = ['location_id', 'customer_id', 'payment_id', 'to', 'subject', 'message', 'status', 'error_message'];

    public function location() { return $this->belongsTo(Location::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function payment() { return $this->belongsTo(Payment::class); }
}
