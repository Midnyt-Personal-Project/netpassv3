<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    public function record(string $action, string $description, ?int $userId = null, ?string $ipAddress = null): void
    {
        ActivityLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'description' => $description,
            'ip_address' => $ipAddress ?? request()?->ip(),
        ]);
    }
}
