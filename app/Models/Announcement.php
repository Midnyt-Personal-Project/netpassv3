<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'location_id',
        'created_by',
        'customer_id',
        'title',
        'message',
        'priority',
        'is_active',
        'show_ticker',
        'send_sms',
        'starts_at',
        'ends_at',
        'scheduled_at',
        'sent_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'show_ticker' => 'boolean',
        'send_sms' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function isPaused(): bool
    {
        return !$this->is_active;
    }

    public function isSmsSent(): bool
    {
        return $this->send_sms && $this->sent_at !== null;
    }

    public function isSmsScheduled(): bool
    {
        return $this->send_sms && $this->scheduled_at !== null && $this->sent_at === null && $this->is_active;
    }

    /** Announcements whose SMS blast is due but has not been claimed yet. */
    public function scopeDueForSms(Builder $query): Builder
    {
        return $query->where('send_sms', true)
            ->where('is_active', true)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->whereNull('sent_at');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('show_ticker', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
