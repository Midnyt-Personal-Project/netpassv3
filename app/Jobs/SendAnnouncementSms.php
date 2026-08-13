<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\Customer;
use App\Services\ActivityLogger;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class SendAnnouncementSms implements ShouldQueue
{
    use Queueable;

    // Never retry a broadcast: a retry would re-send the same SMS to everyone.
    public int $tries = 1;

    public function __construct(public readonly int $announcementId)
    {
    }

    public function handle(SmsService $sms): void
    {
        // Atomically claim the announcement so a scheduler race or a duplicate
        // dispatch can never send the same blast twice.
        $claimed = DB::table('announcements')
            ->where('id', $this->announcementId)
            ->whereNull('sent_at')
            ->update(['sent_at' => now(), 'updated_at' => now()]);

        if ($claimed !== 1) {
            return;
        }

        $announcement = Announcement::with('customer')->find($this->announcementId);

        if (!$announcement) {
            return;
        }

        $text = trim(($announcement->title ? $announcement->title.': ' : '').$announcement->message);

        $query = Customer::query()->whereNotNull('phone_number');

        if ($announcement->customer_id) {
            // Single recipient announcement.
            $query->whereKey($announcement->customer_id);
        } elseif ($announcement->location_id) {
            // Everyone at one location.
            $query->where('location_id', $announcement->location_id);
        }
        // location_id null => everyone at every location (global blast).

        $sent = 0;
        $failed = 0;

        $query->chunkById(50, function ($customers) use ($sms, $text, &$sent, &$failed) {
            foreach ($customers as $customer) {
                if ($sms->sendSms($customer->phone_number, $text, $customer)) {
                    $sent++;
                } else {
                    $failed++;
                }
            }
        });

        app(ActivityLogger::class)->record(
            'announcement.sms_sent',
            "Announcement SMS blast \"".mb_substr($announcement->title ?: $announcement->message, 0, 60)."\" finished: {$sent} sent, {$failed} failed."
        );
    }
}
