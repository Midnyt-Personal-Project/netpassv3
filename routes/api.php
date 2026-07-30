<?php

use App\Http\Controllers\Api\ApiController;
use Illuminate\Support\Facades\Route;

/* Router endpoints use the router ID/token headers, not browser authentication. */
Route::prefix('api/router')->middleware('throttle:120,1')->group(function () {
    Route::post('heartbeat', [ApiController::class, 'heartbeat']);
    Route::get('commands', [ApiController::class, 'fetchCommands']);
    Route::post('commands/{id}/ack', [ApiController::class, 'acknowledgeCommand'])->whereNumber('id');
    Route::get('data', [ApiController::class, 'pullData']);
});
