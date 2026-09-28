<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BuildingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LeaseController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\UnitController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::prefix('auth')->group(function () {
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});
Route::middleware('hardcoded.token')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);

    Route::apiResource('properties', PropertyController::class);
    Route::get('/properties/{property}/occupancy', [PropertyController::class, 'occupancy']);
    Route::get('/properties/export', [PropertyController::class, 'export']);

    Route::apiResource('buildings', BuildingController::class);

    Route::apiResource('units', UnitController::class);
    Route::patch('/units/{unit}/status', [UnitController::class, 'updateStatus']);

    Route::apiResource('tenants', TenantController::class);
    Route::get('/tenants/{tenant}/leases', [TenantController::class, 'leases']);
    Route::get('/tenants/{tenant}/documents', [TenantController::class, 'documents']);

    Route::apiResource('leases', LeaseController::class);
    Route::get('/leases/{lease}/pdf', [LeaseController::class, 'pdf'])->name('leases.pdf');
    Route::post('/leases/{lease}/terminate', [LeaseController::class, 'terminate']);
    Route::post('/leases/{lease}/renew', [LeaseController::class, 'renew']);
    Route::post('/leases/{lease}/sign', [LeaseController::class, 'sign']);
    Route::get('/leases/{lease}/document', [LeaseController::class, 'document']);

    Route::apiResource('invoices', InvoiceController::class)->only(['index', 'show', 'update', 'store', 'destroy']);
    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send']);
    Route::get('/invoices/{invoice}/receipt', [InvoiceController::class, 'receiptPdf']);
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);
    Route::post('/invoices/bulk-generate', [InvoiceController::class, 'bulkGenerate']);
    Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'storePayment']);
    Route::get('/invoices/{invoice}/payments', [InvoiceController::class, 'payments']);

    Route::apiResource('payments', PaymentController::class)->only(['index', 'store', 'show']);

});

Route::middleware(['auth:sanctum', 'throttle:api'])
    ->prefix('dashboard')
    ->group(function () {
        Route::get('/stats', [DashboardController::class, 'stats']);
        Route::get('/revenue-trend', [DashboardController::class, 'revenueTrend']);
        Route::get('/expiring-leases', [DashboardController::class, 'expiringLeases']);
        Route::get('/occupancy-chart', [DashboardController::class, 'occupancyChart']);
        Route::get('/revenue-chart', [DashboardController::class, 'revenueChart']);
        Route::get('/expiring-leases-chart', [DashboardController::class, 'expiringLeasesChart']);
        Route::get('/recent-activity', [DashboardController::class, 'recentActivity']);
        Route::get('/property-performance/{property}', [DashboardController::class, 'propertyPerformance']);
    });
