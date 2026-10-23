<?php

use App\Http\Controllers\Api\FolioDeductionController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PalletController;
use App\Http\Controllers\Api\V1\PalletInspectionController;
use App\Http\Controllers\FieldManagementController;
use App\Http\Controllers\MobileAuthController;
use App\Http\Controllers\PreCooling\IntegracionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/pallets/{folio}', [PalletController::class, 'show']);
        Route::post('/inspections', [PalletInspectionController::class, 'store']);
    });
});

Route::post('/mobile/login', [MobileAuthController::class, 'login'])->name('api.mobile.login');
Route::post('/folio/login', [MobileAuthController::class, 'folioLogin'])->name('api.folio.login');

Route::get('/prefrio/integracion', [IntegracionController::class, 'index'])
    ->middleware('prefrio.api.key')
    ->name('api.prefrio.integracion');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/field-management/sync', [FieldManagementController::class, 'apiSync'])
        ->name('api.field-management.sync');

    Route::post('/deduct', [FolioDeductionController::class, 'deduct'])
        ->name('api.folio.deduct');
});
