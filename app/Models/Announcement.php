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

    public function smsLogs()
    {
        return $this->hasMany(SmsLog::class);
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

    /** Announcements whose SMS blast is due but not finished yet. */
    public function scopeDueForSms(Builder $query): Builder
    {
        return $query->where('send_sms', true)
            ->where('is_active', true)
            ->whereNull('sent_at')
            ->where(fn (Builder $query) => $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()));
    }

    /** Customers this announcement's SMS should reach (single, location, or global). */
    public function smsRecipientQuery(): Builder
    {
        $query = Customer::query()->whereNotNull('phone_number');

        if ($this->customer_id) {
            $query->whereKey($this->customer_id);
        } elseif ($this->location_id) {
            $query->where('location_id', $this->location_id);
        }

        return $query;
    }

    /**
     * Recipients that still need this announcement's SMS. A recipient counts
     * as done once one SMS succeeded, or after two recorded failures (so a
     * permanently bad number can't block the blast forever).
     */
    public function pendingSmsRecipients(): Builder
    {
        $id = $this->id;

        return $this->smsRecipientQuery()
            ->where(function (Builder $query) use ($id) {
                $query->whereDoesntHave('smsLogs', fn (Builder $logs) => $logs->where('announcement_id', $id)->where('status', 'sent'))
                    ->where(function (Builder $query) use ($id) {
                        $query->whereDoesntHave('smsLogs', fn (Builder $logs) => $logs->where('announcement_id', $id)->where('status', 'failed'))
                            ->orWhereHas('smsLogs', fn (Builder $logs) => $logs->where('announcement_id', $id)->where('status', 'failed'), '<', 2);
                    });
            });
    }

    /** Mark the SMS blast finished when no recipient is pending anymore. */
    public function markSmsFinishedIfDone(): bool
    {
        if ($this->sent_at !== null) {
            return false;
        }

        $pending = $this->pendingSmsRecipients()->exists();

        if (!$pending) {
            $this->update(['sent_at' => now()]);
        }

        return !$pending;
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('show_ticker', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
