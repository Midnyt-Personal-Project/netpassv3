<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\PaystackWebhookController;

/* Router endpoints use the router ID/token headers, not browser authentication. */
Route::post('/paystack/webhook', PaystackWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('paystack.webhook');

Route::prefix('/router')->middleware('throttle:120,1')->group(function () {
    Route::post('heartbeat', [ApiController::class, 'heartbeat']);
    Route::get('commands', [ApiController::class, 'fetchCommands']);
    Route::post('commands/{id}/ack', [ApiController::class, 'acknowledgeCommand'])->whereNumber('id');
    Route::get('data', [ApiController::class, 'pullData']);
});


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


