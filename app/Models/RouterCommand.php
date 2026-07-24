<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouterCommand extends Model
{
    protected $fillable = [
        'router_id',
        'command_type',
        'payload',
        'status',
        'executed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'executed_at' => 'datetime',
    ];

    public function router()
    {
        return $this->belongsTo(Router::class);
    }
}
