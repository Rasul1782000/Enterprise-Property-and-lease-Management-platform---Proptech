<?php

use App\Http\Controllers\Api\BouncedChequeController;
use App\Http\Controllers\Api\BuildingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EjariContractController;
use App\Http\Controllers\Api\EscrowAccountController;
use App\Http\Controllers\Api\FitOutRequestController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LeaseAgreementController;
use App\Http\Controllers\Api\LeaseController;
use App\Http\Controllers\Api\LeaseRenewalController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PostDatedChequeController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\ServiceChargeController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\VaultAssetController;
use App\Http\Controllers\Api\WhatsAppController;
use App\Http\Controllers\Api\WhatsAppStreamController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware([])->group(function () {
    Route::get('/properties/export', [PropertyController::class, 'export']);
    Route::apiResource('properties', PropertyController::class);
    Route::get('/properties/{property}/occupancy', [PropertyController::class, 'occupancy']);
    Route::post('/properties/{property}/image', [PropertyController::class, 'uploadImage']);

    Route::apiResource('buildings', BuildingController::class);

    Route::apiResource('units', UnitController::class);
    Route::patch('/units/{unit}/status', [UnitController::class, 'updateStatus']);

    Route::apiResource('tenants', TenantController::class);
    Route::get('/tenants/{tenant}/leases', [TenantController::class, 'leases']);
    Route::get('/tenants/{tenant}/documents', [TenantController::class, 'documents']);
    Route::post('/tenants/{tenant}/documents', [TenantController::class, 'storeDocument']);
    Route::get('/tenants/{tenant}/documents/{document}/download', [TenantController::class, 'downloadDocument'])
        ->name('tenants.documents.download');
    Route::delete('/tenants/{tenant}/documents/{document}', [TenantController::class, 'destroyDocument']);

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

    // Operations Module Routes - Stats routes MUST come before apiResource to avoid parameter conflicts
    Route::get('/vault-assets/stats', [VaultAssetController::class, 'stats']);
    Route::apiResource('vault-assets', VaultAssetController::class);
    Route::post('/vault-assets/{vaultAsset}/release', [VaultAssetController::class, 'release']);

    Route::get('/post-dated-cheques/stats', [PostDatedChequeController::class, 'stats']);
    Route::apiResource('post-dated-cheques', PostDatedChequeController::class);
    Route::post('/post-dated-cheques/{postDatedCheque}/deposit', [PostDatedChequeController::class, 'deposit']);
    Route::post('/post-dated-cheques/{postDatedCheque}/clear', [PostDatedChequeController::class, 'clear']);
    Route::post('/post-dated-cheques/{postDatedCheque}/return', [PostDatedChequeController::class, 'return']);

    Route::get('/lease-agreements/stats', [LeaseAgreementController::class, 'stats']);
    Route::apiResource('lease-agreements', LeaseAgreementController::class);
    Route::post('/lease-agreements/{leaseAgreement}/send', [LeaseAgreementController::class, 'send']);
    Route::post('/lease-agreements/{leaseAgreement}/sign-landlord', [LeaseAgreementController::class, 'signLandlord']);
    Route::post('/lease-agreements/{leaseAgreement}/sign-tenant', [LeaseAgreementController::class, 'signTenant']);
    Route::post('/lease-agreements/{leaseAgreement}/issue-ejari', [LeaseAgreementController::class, 'issueEjari']);

    Route::get('/lease-renewals/stats', [LeaseRenewalController::class, 'stats']);
    Route::apiResource('lease-renewals', LeaseRenewalController::class);
    Route::post('/lease-renewals/{leaseRenewal}/send-offer', [LeaseRenewalController::class, 'sendOffer']);
    Route::post('/lease-renewals/{leaseRenewal}/negotiate', [LeaseRenewalController::class, 'negotiate']);
    Route::post('/lease-renewals/{leaseRenewal}/accept', [LeaseRenewalController::class, 'accept']);
    Route::post('/lease-renewals/{leaseRenewal}/decline', [LeaseRenewalController::class, 'decline']);

    Route::get('/service-charges/stats', [ServiceChargeController::class, 'stats']);
    Route::apiResource('service-charges', ServiceChargeController::class);
    Route::post('/service-charges/{serviceCharge}/issue', [ServiceChargeController::class, 'issue']);
    Route::post('/service-charges/{serviceCharge}/mark-paid', [ServiceChargeController::class, 'markPaid']);

    Route::get('/escrow-accounts/stats', [EscrowAccountController::class, 'stats']);
    Route::apiResource('escrow-accounts', EscrowAccountController::class);
    Route::post('/escrow-accounts/{escrowAccount}/freeze', [EscrowAccountController::class, 'freeze']);
    Route::post('/escrow-accounts/{escrowAccount}/activate', [EscrowAccountController::class, 'activate']);
    Route::post('/escrow-accounts/{escrowAccount}/reconcile', [EscrowAccountController::class, 'reconcile']);

    Route::get('/ejari-contracts/stats', [EjariContractController::class, 'stats']);
    Route::apiResource('ejari-contracts', EjariContractController::class);
    Route::post('/ejari-contracts/{ejariContract}/submit', [EjariContractController::class, 'submit']);
    Route::post('/ejari-contracts/{ejariContract}/register', [EjariContractController::class, 'register']);
    Route::post('/ejari-contracts/{ejariContract}/reject', [EjariContractController::class, 'reject']);
    Route::post('/ejari-contracts/{ejariContract}/resubmit', [EjariContractController::class, 'resubmit']);
    Route::post('/ejari-contracts/{ejariContract}/cancel', [EjariContractController::class, 'cancel']);

    Route::get('/fit-out-requests/stats', [FitOutRequestController::class, 'stats']);
    Route::apiResource('fit-out-requests', FitOutRequestController::class);
    Route::post('/fit-out-requests/{fitOutRequest}/submit-permit', [FitOutRequestController::class, 'submitPermit']);
    Route::post('/fit-out-requests/{fitOutRequest}/approve-permit', [FitOutRequestController::class, 'approvePermit']);
    Route::post('/fit-out-requests/{fitOutRequest}/reject-permit', [FitOutRequestController::class, 'rejectPermit']);
    Route::post('/fit-out-requests/{fitOutRequest}/schedule-inspection', [FitOutRequestController::class, 'scheduleInspection']);
    Route::post('/fit-out-requests/{fitOutRequest}/issue-noc', [FitOutRequestController::class, 'issueNoc']);
    Route::post('/fit-out-requests/{fitOutRequest}/update-progress', [FitOutRequestController::class, 'updateProgress']);

    Route::get('/bounced-cheques/stats', [BouncedChequeController::class, 'stats']);
    Route::apiResource('bounced-cheques', BouncedChequeController::class);
    Route::post('/bounced-cheques/{bouncedCheque}/notify', [BouncedChequeController::class, 'notify']);
    Route::post('/bounced-cheques/{bouncedCheque}/promise-to-pay', [BouncedChequeController::class, 'promiseToPay']);
    Route::post('/bounced-cheques/{bouncedCheque}/partial-recovery', [BouncedChequeController::class, 'partialRecovery']);
    Route::post('/bounced-cheques/{bouncedCheque}/recover', [BouncedChequeController::class, 'recover']);
    Route::post('/bounced-cheques/{bouncedCheque}/escalate', [BouncedChequeController::class, 'escalate']);
    Route::post('/bounced-cheques/{bouncedCheque}/legal-action', [BouncedChequeController::class, 'legalAction']);
    Route::post('/bounced-cheques/{bouncedCheque}/write-off', [BouncedChequeController::class, 'writeOff']);

    Route::prefix('whatsapp')->group(function () {
        Route::get('/status', [WhatsAppController::class, 'status']);
        Route::post('/send', [WhatsAppController::class, 'send']);
        Route::get('/history', [WhatsAppController::class, 'history']);
        Route::get('/qr', [WhatsAppController::class, 'qr']);
        Route::post('/session/start', [WhatsAppController::class, 'startSession']);
        Route::post('/session/create', [WhatsAppController::class, 'createSession']);
    });
});

Route::get('/whatsapp/stream', [WhatsAppStreamController::class, 'stream']);

Route::prefix('dashboard')
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

Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle']);