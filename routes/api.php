<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\{ApiController, PaystackWebhookController, VoucherController};

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

/* Public endpoint hit directly from the hotspot login page's browser JS. */
Route::get('/voucher/{code}/status', [VoucherController::class, 'status'])
    ->middleware('throttle:30,1')
    ->where('code', '[A-Za-z0-9\-]+')
    ->name('voucher.status');


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


