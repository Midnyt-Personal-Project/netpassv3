<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = [
        'admin_id',
        'name',
        'slug',
        'paystack_subaccount',
        'commission_percentage',
        'status',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function routers()
    {
        return $this->hasMany(Router::class);
    }

    public function packages()
    {
        return $this->hasMany(Package::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
