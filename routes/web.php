<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Customer\CustomerPortalController;
use App\Http\Controllers\SuperAdmin\SuperAdminController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// CUSTOMER PORTAL & HOTSPOT Splash (slug-based)
Route::group(['prefix' => 'h'], function () {
    Route::get('{slug}', [CustomerPortalController::class, 'showPortal'])->name('customer.portal');
    Route::post('{slug}/checkout', [CustomerPortalController::class, 'checkout'])->name('customer.checkout');
    Route::get('{slug}/callback', [CustomerPortalController::class, 'paymentCallback'])->name('customer.payment.callback');
    Route::get('{slug}/success', [CustomerPortalController::class, 'showSuccess'])->name('customer.success');
    
    // Smart Device Registration Feature
    Route::post('{slug}/device/register', [CustomerPortalController::class, 'registerDevice'])->name('customer.device.register');
    Route::delete('{slug}/device/{id}/remove', [CustomerPortalController::class, 'removeDevice'])->name('customer.device.remove');
});

// SUPER ADMIN DASHBOARD
Route::group(['prefix' => 'superadmin'], function () {
    Route::get('/', [SuperAdminController::class, 'dashboard'])->name('superadmin.dashboard');
    Route::post('admin/create', [SuperAdminController::class, 'createAdmin'])->name('superadmin.admin.create');
    Route::post('location/create', [SuperAdminController::class, 'createLocation'])->name('superadmin.location.create');
    Route::post('router/create', [SuperAdminController::class, 'createRouter'])->name('superadmin.router.create');
    Route::post('admin/{id}/toggle', [SuperAdminController::class, 'toggleAdminStatus'])->name('superadmin.admin.toggle');
});

// ADMIN DASHBOARD
Route::group(['prefix' => 'admin'], function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('packages', [AdminController::class, 'showPackages'])->name('admin.packages');
    Route::post('packages/create', [AdminController::class, 'createPackage'])->name('admin.packages.create');
    Route::get('devices', [AdminController::class, 'showDevices'])->name('admin.devices');
    Route::post('devices/{id}/toggle', [AdminController::class, 'toggleDeviceStatus'])->name('admin.devices.toggle');
});
