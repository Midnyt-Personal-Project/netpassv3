<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Customer\CustomerPortalController;
use App\Http\Controllers\SuperAdmin\SuperAdminController;

Route::view('/', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.attempt');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Public customer hotspot portal.
Route::prefix('h')->group(function () {
    Route::get('{slug}', [CustomerPortalController::class, 'showPortal'])->name('customer.portal');
    Route::get('{slug}/subscription-status', [CustomerPortalController::class, 'showSubscriptionStatus'])->name('customer.subscription-status');
    Route::post('{slug}/checkout', [CustomerPortalController::class, 'checkout'])->middleware('throttle:10,1')->name('customer.checkout');
    Route::get('{slug}/callback', [CustomerPortalController::class, 'paymentCallback'])->name('customer.payment.callback');
    Route::get('{slug}/success', [CustomerPortalController::class, 'showSuccess'])->name('customer.success');
    Route::post('{slug}/device/register', [CustomerPortalController::class, 'registerDevice'])->name('customer.device.register');
    Route::delete('{slug}/device/{id}/remove', [CustomerPortalController::class, 'removeDevice'])->name('customer.device.remove');
});

Route::prefix('superadmin')->middleware(['auth', 'role:super_admin'])->group(function () {
    Route::get('/', [SuperAdminController::class, 'dashboard'])->name('superadmin.dashboard');
    Route::post('admin/create', [SuperAdminController::class, 'createAdmin'])->name('superadmin.admin.create');
    Route::post('location/create', [SuperAdminController::class, 'createLocation'])->name('superadmin.location.create');
    Route::post('router/create', [SuperAdminController::class, 'createRouter'])->name('superadmin.router.create');
    Route::get('router-commands', [SuperAdminController::class, 'showRouterCommands'])->name('superadmin.router-commands');
    Route::get('routers', [SuperAdminController::class, 'showRouters'])->name('superadmin.routers');
    Route::post('admin/{id}/toggle', [SuperAdminController::class, 'toggleAdminStatus'])->name('superadmin.admin.toggle');
});

// A super admin deliberately has every admin capability, across every location.
Route::prefix('admin')->middleware(['auth', 'role:admin,super_admin'])->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('packages', [AdminController::class, 'showPackages'])->name('admin.packages');
    Route::post('packages/create', [AdminController::class, 'createPackage'])->name('admin.packages.create');
    Route::get('devices', [AdminController::class, 'showDevices'])->name('admin.devices');
    Route::post('devices/{id}/toggle', [AdminController::class, 'toggleDeviceStatus'])->name('admin.devices.toggle');
    Route::get('announcements', [AdminController::class, 'showAnnouncements'])->name('admin.announcements');
    Route::get('logs', [AdminController::class, 'showLogs'])->name('admin.logs');
    Route::post('announcements', [AdminController::class, 'createAnnouncement'])->name('admin.announcements.create');
    Route::get('subscriptions', [AdminController::class, 'showSubscriptions'])->name('admin.subscriptions');
    Route::post('subscriptions/create', [AdminController::class, 'createSubscription'])->name('admin.subscriptions.create');
    Route::post('locations/{location}/subscription-notifications', [AdminController::class, 'updateSubscriptionNotifications'])->name('admin.locations.subscription-notifications');
});
Route::post('subscriptions/block/{id}', [AdminController::class, 'blockSubscription'])->name('admin.subscriptions.block');
Route::post('subscriptions/remove/{id}', [AdminController::class, 'removeSubscription'])->name('admin.subscriptions.remove');
