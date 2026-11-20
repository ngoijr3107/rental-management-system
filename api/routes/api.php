<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LeaseController;
use App\Http\Controllers\Api\MaintenanceRequestController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\UnitPhotoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
/*
| These are the only endpoints reachable without a token, so they carry their
| own rate limit. Six login attempts a minute per IP stops credential
| stuffing without getting in a real user's way.
*/
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

/*
|--------------------------------------------------------------------------
| Authenticated routes (Sanctum bearer token)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);

    // Your own account — available to every role, including viewers.
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
        ->middleware('throttle:6,1');
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard', DashboardController::class);

    Route::apiResource('properties', PropertyController::class);
    Route::apiResource('units', UnitController::class);
    Route::apiResource('tenants', TenantController::class);

    // Photos of a unit — 'picha' on the listing.
    Route::get('/units/{unit}/photos', [UnitPhotoController::class, 'index']);
    Route::post('/units/{unit}/photos', [UnitPhotoController::class, 'store']);
    Route::post('/units/{unit}/photos/{photo}/cover', [UnitPhotoController::class, 'setCover']);
    Route::delete('/units/{unit}/photos/{photo}', [UnitPhotoController::class, 'destroy']);

    Route::apiResource('leases', LeaseController::class)->except('destroy');
    Route::post('/leases/{lease}/terminate', [LeaseController::class, 'terminate']);
    Route::post('/leases/{lease}/invoices', [InvoiceController::class, 'storeForLease']);

    Route::post('/invoices/generate', [InvoiceController::class, 'generate']);
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);
    Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store']);

    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    Route::delete('/payments/{payment}', [PaymentController::class, 'destroy']);

    Route::apiResource('maintenance-requests', MaintenanceRequestController::class)
        ->parameters(['maintenance-requests' => 'maintenance_request']);

    /*
    | Administration — team access, account settings, audit trail.
    */
    Route::get('/team/roles', [TeamController::class, 'roles']);
    Route::get('/team', [TeamController::class, 'index']);
    Route::post('/team', [TeamController::class, 'store']);
    Route::put('/team/{member}', [TeamController::class, 'update']);
    Route::delete('/team/{member}', [TeamController::class, 'destroy']);

    Route::get('/settings', [SettingsController::class, 'show']);
    Route::put('/settings', [SettingsController::class, 'update']);
    Route::post('/settings/logo', [SettingsController::class, 'uploadLogo']);
    Route::delete('/settings/logo', [SettingsController::class, 'deleteLogo']);

    Route::get('/activity', [ActivityLogController::class, 'index']);

    /*
    | Notifications — in-app alerts for staff, outbound messages for tenants.
    */
    Route::get('/alerts', [AlertController::class, 'index']);
    Route::post('/alerts/read-all', [AlertController::class, 'readAll']);
    Route::post('/alerts/{alert}/read', [AlertController::class, 'read']);
    Route::get('/outbox', [AlertController::class, 'outbox']);

    /*
    | Reports — read-only analytics across the whole portfolio.
    */
    Route::prefix('reports')->group(function () {
        Route::get('/rent-roll', [ReportController::class, 'rentRoll']);
        Route::get('/arrears', [ReportController::class, 'arrears']);
        Route::get('/collections', [ReportController::class, 'collections']);
        Route::get('/vacancy', [ReportController::class, 'vacancy']);
        Route::get('/renewals', [ReportController::class, 'renewals']);
        Route::get('/property-performance', [ReportController::class, 'propertyPerformance']);
        Route::get('/maintenance', [ReportController::class, 'maintenance']);
        Route::get('/tenant-statement/{tenant}', [ReportController::class, 'tenantStatement']);
    });
});
