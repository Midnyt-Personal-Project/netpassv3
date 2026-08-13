<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Services\AnnouncementSmsSender;
use Illuminate\Console\Command;

class SendDueAnnouncements extends Command
{
    protected $signature = 'announcements:send-due {--limit=100 : Maximum SMS to send in this run}';

    protected $description = 'Send the next batch of SMS for announcement blasts whose scheduled time has arrived.';

    public function handle(AnnouncementSmsSender $sender): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $due = Announcement::dueForSms()->orderBy('scheduled_at')->limit(10)->get();

        $totalSent = 0;
        $totalFailed = 0;

        foreach ($due as $announcement) {
            $result = $sender->sendChunk($announcement, $limit);

            $totalSent += $result['sent'];
            $totalFailed += $result['failed'];
            $limit -= ($result['sent'] + $result['failed']);

            if ($result['sent'] > 0 || $result['failed'] > 0) {
                $this->info(sprintf(
                    'Announcement #%d: %d sent, %d failed%s',
                    $announcement->id,
                    $result['sent'],
                    $result['failed'],
                    $result['finished'] ? ' (finished)' : ''
                ));
            }

            if ($limit <= 0) {
                break;
            }
        }

        if ($totalSent === 0 && $totalFailed === 0) {
            $this->info('No announcement SMS to send this minute.');
        } else {
            $this->info("Announcement SMS run: {$totalSent} sent, {$totalFailed} failed.");
        }

        return self::SUCCESS;
    }
}
