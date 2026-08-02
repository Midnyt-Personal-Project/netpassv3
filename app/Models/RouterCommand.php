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

    protected $appends = [
        'script',
    ];

    protected $casts = [
        'payload' => 'array',
        'executed_at' => 'datetime',
    ];

    public function getScriptAttribute(): string
    {
        return $this->toScript();
    }

    /**
     * Generate the executable RouterOS script for this command.
     */
    public function toScript(): string
    {
        $payload = $this->payload ?? [];
        $escape = fn ($val) => str_replace('"', '\"', (string) $val);

        $formatDuration = function ($minutes) {
            if (!$minutes || $minutes <= 0) {
                return '0s';
            }
            if ($minutes % 1440 === 0) {
                return ($minutes / 1440) . 'd';
            }
            if ($minutes % 60 === 0) {
                return ($minutes / 60) . 'h';
            }
            return $minutes . 'm';
        };

        switch ($this->command_type) {
            case 'CREATE_PROFILE':
                $name = $escape($payload['name'] ?? 'default');
                $speedDown = (string) ($payload['speed_down'] ?? '0');
                $speedUp = (string) ($payload['speed_up'] ?? '0');
                $sharedUsers = (int) ($payload['share_users'] ?? 1);
                $durationMinutes = (int) ($payload['duration_minutes'] ?? 0);
                $timeout = $formatDuration($durationMinutes);

                $rateLimitLine = '';
                if (($speedDown !== '0' && $speedDown !== '') || ($speedUp !== '0' && $speedUp !== '')) {
                    $rateLimit = "{$speedDown}/{$speedUp}";
                    $rateLimitLine = " \\\n        rate-limit=\"{$rateLimit}\"";
                }

                return ':local ProfileIds [/ip hotspot user profile find where name="' . $name . '"]' . "\n\n"
                    . ':if ([:len $ProfileIds]=0) do={' . "\n"
                    . '    /ip hotspot user profile add \\' . "\n"
                    . '        name="' . $name . '" \\' . "\n"
                    . '        shared-users=' . $sharedUsers . ' \\' . "\n"
                    . '        session-timeout=' . $timeout . ' \\' . "\n"
                    . '        mac-cookie-timeout=' . $timeout . $rateLimitLine . "\n"
                    . '} else={' . "\n"
                    . '    /ip hotspot user profile set \\' . "\n"
                    . '        $ProfileIds \\' . "\n"
                    . '        shared-users=' . $sharedUsers . ' \\' . "\n"
                    . '        session-timeout=' . $timeout . ' \\' . "\n"
                    . '        mac-cookie-timeout=' . $timeout . $rateLimitLine . "\n"
                    . '}';

            case 'CREATE_USER':
                $username = $escape($payload['username'] ?? $payload['voucher_code'] ?? $payload['voucher'] ?? '');
                $password = $username;
                $profile = $escape($payload['profile'] ?? 'default');
                $durationMinutes = (int) ($payload['duration_minutes'] ?? 0);
                $uptimeLine = $durationMinutes > 0 ? " \\\n        limit-uptime=" . $formatDuration($durationMinutes) : '';

                return ':local UserIds [/ip hotspot user find where name="' . $username . '"]' . "\n\n"
                    . ':if ([:len $UserIds]=0) do={' . "\n"
                    . '    /ip hotspot user add \\' . "\n"
                    . '        name="' . $username . '" \\' . "\n"
                    . '        password="' . $password . '" \\' . "\n"
                    . '        profile="' . $profile . '"' . $uptimeLine . "\n"
                    . '} else={' . "\n"
                    . '    /ip hotspot user set \\' . "\n"
                    . '        $UserIds \\' . "\n"
                    . '        password="' . $password . '" \\' . "\n"
                    . '        profile="' . $profile . '" \\' . "\n"
                    . '        disabled=no' . $uptimeLine . "\n"
                    . '}';

            case 'ADD_MAC':
                $mac = $escape($payload['mac'] ?? '');
                $username = $escape($payload['username'] ?? '');
                $comment = $escape("Oyalo:{$username}");

                return ':foreach i in=[/ip hotspot ip-binding find where mac-address="' . $mac . '"] do={' . "\n"
                    . '    /ip hotspot ip-binding remove $i' . "\n"
                    . '}' . "\n"
                    . '/ip hotspot ip-binding add \\' . "\n"
                    . '    mac-address="' . $mac . '" \\' . "\n"
                    . '    type=bypassed \\' . "\n"
                    . '    comment="' . $comment . '"';

            case 'REMOVE_MAC':
                $mac = $escape($payload['mac'] ?? '');

                return ':foreach i in=[/ip hotspot ip-binding find where mac-address="' . $mac . '"] do={' . "\n"
                    . '    /ip hotspot ip-binding remove $i' . "\n"
                    . '}';

            case 'DISABLE_USER':
                $username = $escape($payload['username'] ?? '');

                return '/ip hotspot user disable [find where name="' . $username . '"]' . "\n"
                    . '/ip hotspot active remove [find where user="' . $username . '"]';

            case 'REMOVE_USER':
                $username = $escape($payload['username'] ?? '');

                return '/ip hotspot user remove [find where name="' . $username . '"]' . "\n"
                    . '/ip hotspot active remove [find where user="' . $username . '"]';

            default:
                return '';
        }
    }

    public function router()
    {
        return $this->belongsTo(Router::class);
    }
}
