<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Router extends Model
{
    protected $fillable = [
        'location_id',
        'router_id',
        'api_token',
        'name',
        'model',
        'last_heartbeat',
        'status',
    ];

    protected $casts = [
        'last_heartbeat' => 'datetime',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function commands()
    {
        return $this->hasMany(RouterCommand::class);
    }

    public function pendingCommands()
    {
        return $this->hasMany(RouterCommand::class)->where('status', 'pending');
    }
}
