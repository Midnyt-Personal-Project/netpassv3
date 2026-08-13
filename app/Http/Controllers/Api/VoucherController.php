<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Models\RouterCommand;

class VoucherController extends Controller
{
    /**
     * Public status check used by the hotspot login page to poll whether
     * a voucher's CREATE_USER command has been acknowledged by the router.
     *
     * RouterCommand::toScript() reads the username from payload['username'],
     * payload['voucher_code'], or payload['voucher'] depending on how the
     * command was created — so this checks all three keys.
     *
     * Returns:
     *   - 404 { status: "not_found" }  -> no matching CREATE_USER command
     *   -     { status: "pending" }    -> command exists, router hasn't ACKed yet
     *   -     { status: "ready" }      -> router ACKed as "completed"
     *   -     { status: "failed" }     -> router ACKed as "failed"
     */
    public function status(Request $request, string $code)
    {
        $code = strtoupper(trim($code));
        if (!str_starts_with($code, 'OY-')) {
            $code = 'OY-' . $code;
        }

        $command = RouterCommand::where('command_type', 'CREATE_USER')
            ->where(function ($query) use ($code) {
                $query->whereJsonContains('payload->username', $code)
                    ->orWhereJsonContains('payload->voucher_code', $code)
                    ->orWhereJsonContains('payload->voucher', $code);
            })
            ->latest()
            ->first();

        if (!$command) {
            return response()->json(['status' => 'not_found'], 404);
        }

        $status = match ($command->status) {
            'completed' => 'ready',
            'failed'    => 'failed',
            default     => 'pending',
        };

        return response()->json(['status' => $status]);
    }
}