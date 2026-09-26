<?php

use App\Http\Controllers\Api\{
    AuthController, BuildingController, DashboardController,
    InvoiceController, LeaseController, PaymentController,
    PropertyController, TenantController, UnitController
};
use Illuminate\Support\Facades\Route;

// Public
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

Route::middleware('hardcoded.token')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::apiResource('properties', PropertyController::class);
    Route::apiResource('buildings', BuildingController::class);
    Route::apiResource('units', UnitController::class);
    Route::apiResource('tenants', TenantController::class);
    Route::apiResource('leases', LeaseController::class);
    Route::get('/leases/{lease}/pdf', [LeaseController::class, 'pdf'])->name('leases.pdf');
    Route::post('/leases/{lease}/terminate', [LeaseController::class, 'terminate']);
    Route::post('/leases/{lease}/renew', [LeaseController::class, 'renew']);

    Route::apiResource('invoices', InvoiceController::class)->only(['index','show','update']);
    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send']);
    Route::get('/invoices/{invoice}/receipt', [InvoiceController::class, 'receiptPdf']);

    Route::apiResource('payments', PaymentController::class)->only(['index','store','show']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/revenue-trend', [DashboardController::class, 'revenueTrend']);
    Route::get('/dashboard/expiring-leases', [DashboardController::class, 'expiringLeases']);
});
