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

        switch ($this->command_type) {
            case 'CREATE_PROFILE':
                $name = $escape($payload['name'] ?? 'default');
                $speedDown = (string) ($payload['speed_down'] ?? '0');
                $speedUp = (string) ($payload['speed_up'] ?? '0');
                $sharedUsers = (int) ($payload['share_users'] ?? 1);

                $rateLimit = '';
                if (($speedDown !== '0' && $speedDown !== '') || ($speedUp !== '0' && $speedUp !== '')) {
                    $rateLimit = "{$speedDown}/{$speedUp}";
                }

                return ':local ProfileIds [/ip hotspot user profile find where name="' . $name . '"]; '
                    . ':if ([:len $ProfileIds] = 0) do={ '
                    . '/ip hotspot user profile add name="' . $name . '" rate-limit="' . $rateLimit . '" shared-users=' . $sharedUsers . '; '
                    . '} else={ '
                    . '/ip hotspot user profile set $ProfileIds rate-limit="' . $rateLimit . '" shared-users=' . $sharedUsers . '; '
                    . '}';

            case 'CREATE_USER':
                $username = $escape($payload['username'] ?? $payload['voucher_code'] ?? $payload['voucher'] ?? '');
                $password = $username;
                $profile = $escape($payload['profile'] ?? 'default');
                $durationMinutes = (int) ($payload['duration_minutes'] ?? 0);
                $uptime = $durationMinutes > 0 ? "{$durationMinutes}m" : '0s';

                return ':local UserIds [/ip hotspot user find where name="' . $username . '"]; '
                    . ':if ([:len $UserIds] = 0) do={ '
                    . '/ip hotspot user add name="' . $username . '" password="' . $password . '" profile="' . $profile . '" limit-uptime=' . $uptime . ' comment="Managed by Oyalo"; '
                    . '} else={ '
                    . '/ip hotspot user set $UserIds password="' . $password . '" profile="' . $profile . '" disabled=no limit-uptime=' . $uptime . ' comment="Managed by Oyalo"; '
                    . '/ip hotspot user reset-counters $UserIds; '
                    . '}';

            case 'ADD_MAC':
                $mac = $escape($payload['mac'] ?? '');
                $username = $escape($payload['username'] ?? '');
                $comment = $escape("Oyalo:{$username}");

                return ':local BindingIds [/ip hotspot ip-binding find where mac-address="' . $mac . '"]; '
                    . ':if ([:len $BindingIds] = 0) do={ '
                    . '/ip hotspot ip-binding add mac-address="' . $mac . '" type=bypassed comment="' . $comment . '"; '
                    . '} else={ '
                    . '/ip hotspot ip-binding set $BindingIds type=bypassed disabled=no comment="' . $comment . '"; '
                    . '}';

            case 'REMOVE_MAC':
                $mac = $escape($payload['mac'] ?? '');

                return ':foreach BindingId in=[/ip hotspot ip-binding find where mac-address="' . $mac . '"] do={ '
                    . ':local BindingComment [/ip hotspot ip-binding get $BindingId comment]; '
                    . ':if ([:pick $BindingComment 0 6] = "Oyalo:") do={ '
                    . '/ip hotspot ip-binding remove $BindingId; '
                    . '} '
                    . '}';

            case 'DISABLE_USER':
                $username = $escape($payload['username'] ?? '');

                return ':local UserIds [/ip hotspot user find where name="' . $username . '"]; '
                    . ':if ([:len $UserIds] > 0) do={ /ip hotspot user set $UserIds disabled=yes; }; '
                    . ':local ActiveIds [/ip hotspot active find where user="' . $username . '"]; '
                    . ':if ([:len $ActiveIds] > 0) do={ /ip hotspot active remove $ActiveIds; };';

            case 'REMOVE_USER':
                $username = $escape($payload['username'] ?? '');

                return ':local ActiveIds [/ip hotspot active find where user="' . $username . '"]; '
                    . ':if ([:len $ActiveIds] > 0) do={ /ip hotspot active remove $ActiveIds; }; '
                    . ':local UserIds [/ip hotspot user find where name="' . $username . '"]; '
                    . ':if ([:len $UserIds] > 0) do={ /ip hotspot user remove $UserIds; };';

            default:
                return '';
        }
    }

    public function router()
    {
        return $this->belongsTo(Router::class);
    }
}
