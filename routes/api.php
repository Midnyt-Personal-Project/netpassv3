<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApiController;

/*
|--------------------------------------------------------------------------
| API Routes (Used by MikroTik Router via Fetch/Scheduler)
|--------------------------------------------------------------------------
*/

Route::group(['prefix' => 'router'], function () {
    // Heartbeat
    Route::post('heartbeat', [ApiController::class, 'heartbeat']);
    
    // Command polling
    Route::get('commands', [ApiController::class, 'fetchCommands']);
    
    // Command acknowledgment
    Route::post('commands/{id}/ack', [ApiController::class, 'acknowledgeCommand']);
});
