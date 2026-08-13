<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\SmsLog;

/**
 * Sends announcement SMS blasts directly (no queue worker required).
 *
 * The every-minute cron calls sendChunk() with a small budget per run, so a
 * big blast is spread across a few minutes instead of hammering the gateway.
 * Progress is tracked through SmsLog rows, so a blast can safely resume
 * after a crash without double-sending to anyone.
 */
class AnnouncementSmsSender
{
    public function __construct(
        private readonly SmsService $sms,
        private readonly ActivityLogger $activity,
    ) {}

    public function text(Announcement $announcement): string
    {
        return trim(($announcement->title ? $announcement->title.': ' : '').$announcement->message);
    }

    /**
     * Send the next batch of pending SMS for one announcement.
     *
     * @return array{sent: int, failed: int, finished: bool}
     */
    public function sendChunk(Announcement $announcement, int $limit = 100): array
    {
        $text = $this->text($announcement);
        $sent = 0;
        $failed = 0;

        $pending = $announcement->pendingSmsRecipients()->orderBy('id')->limit($limit)->get();

        foreach ($pending as $customer) {
            if ($this->sms->sendSms($customer->phone_number, $text, $customer, $announcement->id, SmsLog::TYPE_ANNOUNCEMENT)) {
                $sent++;
            } else {
                $failed++;
            }
        }

        $finished = $announcement->markSmsFinishedIfDone();

        if ($finished) {
            $this->activity->record(
                'announcement.sms_sent',
                "Announcement SMS blast \"".mb_substr($announcement->title ?: $announcement->message, 0, 60)."\" finished for {$announcement->smsRecipientQuery()->count()} recipient(s)."
            );
        }

        return ['sent' => $sent, 'failed' => $failed, 'finished' => $finished];
    }
}
