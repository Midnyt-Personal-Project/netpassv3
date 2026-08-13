<?php

namespace App\Console\Commands;

use App\Jobs\SendAnnouncementSms;
use App\Models\Announcement;
use Illuminate\Console\Command;

class SendDueAnnouncements extends Command
{
    protected $signature = 'announcements:send-due';

    protected $description = 'Dispatch SMS blasts for announcements whose scheduled time has arrived.';

    public function handle(): int
    {
        $due = Announcement::dueForSms()
            ->orderBy('scheduled_at')
            ->limit(25)
            ->get();

        foreach ($due as $announcement) {
            SendAnnouncementSms::dispatch($announcement->id);
        }

        if ($due->isNotEmpty()) {
            $this->info("Dispatched {$due->count()} due announcement SMS blast(s).");
        }

        return self::SUCCESS;
    }
}
