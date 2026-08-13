<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Services\AnnouncementSmsSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queue fallback for announcement SMS blasts.
 *
 * Announcements are normally sent by the every-minute scheduler command, but
 * any blast still sitting in the queue (older deployments) goes through this
 * job, which shares the same resume-safe sending logic.
 */
class SendAnnouncementSms implements ShouldQueue
{
    use Queueable;

    // Never retry a broadcast job automatically: the scheduler will resume
    // whatever is still pending on its next run.
    public int $tries = 1;

    public function __construct(public readonly int $announcementId)
    {
    }

    public function handle(AnnouncementSmsSender $sender): void
    {
        $announcement = Announcement::find($this->announcementId);

        if (!$announcement) {
            return;
        }

        $sender->sendChunk($announcement, 200);
    }
}
