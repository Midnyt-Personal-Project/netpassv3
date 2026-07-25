<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

use App\Models\ActivityLog;

class ActivityLogger
{
    public function record(string $action, string $description, ?int $userId = null, ?string $ipAddress = null): void
    {
        ActivityLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'description' => $description,
            'ip_address' => $ipAddress ?? (app()->runningInConsole() ? null : request()->ip()),
        ]);
    }
}
