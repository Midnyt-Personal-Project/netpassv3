<?php

namespace App\Console\Commands;

use App\Services\SmsService;
use Illuminate\Console\Command;

class TestSms extends Command
{
    protected $signature = 'sms:test {phone : Recipient phone number, e.g. 0244123456 or 233244123456} {message? : Message text (defaults to a test message)}';

    protected $description = 'Send a single test SMS to verify the Arkesel key and number format.';

    public function handle(SmsService $sms): int
    {
        $phone = (string) $this->argument('phone');
        $message = $this->argument('message')
            ?: 'Oyalo WiFi test message. If you received this, SMS delivery is working correctly.';

        $this->line("Sending test SMS to {$phone}...");

        if ($sms->sendSms($phone, $message)) {
            $this->info('SMS accepted by the Arkesel gateway.');

            return self::SUCCESS;
        }

        $this->error('SMS was rejected. Check Admin > Logs (SMS delivery log) for the exact reason.');

        return self::FAILURE;
    }
}
