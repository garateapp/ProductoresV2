<?php

use App\Http\Controllers\Api\FolioDeductionController;
use App\Http\Controllers\FieldManagementController;
use App\Http\Controllers\MobileAuthController;
use Illuminate\Support\Facades\Route;

Route::post('/mobile/login', [MobileAuthController::class, 'login'])->name('api.mobile.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/field-management/sync', [FieldManagementController::class, 'apiSync'])
        ->name('api.field-management.sync');

    Route::post('/deduct', [FolioDeductionController::class, 'deduct'])
        ->name('api.folio.deduct');
});
