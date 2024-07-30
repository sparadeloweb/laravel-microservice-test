<?php

use App\Http\Controllers\AccountingController;
use Illuminate\Support\Facades\Route;

Route::prefix('accounting')->group(function () {
    Route::get('receipts', [AccountingController::class, 'getReceipts']);
    Route::get('entries', [AccountingController::class, 'getEntries']);
    Route::get('entries/{id}', [AccountingController::class, 'getEntry']);
    Route::get('sent-cfes', [AccountingController::class, 'getSentCfes']);
    Route::get('settings', [AccountingController::class, 'getSettings']);
    Route::post('save-rut', [AccountingController::class, 'saveRut']);
    Route::post('upload-logo', [AccountingController::class, 'uploadLogo']);
});
