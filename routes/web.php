<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PhotoTypeController;
use App\Http\Controllers\QualityControlPhotoController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    Route::resource('users', UserController::class)->names('users');
    Route::get('users/{user}/assign-roles', [UserController::class, 'assignRoles'])->name('users.assignRoles');
    Route::post('users/{user}/sync-roles', [UserController::class, 'syncRoles'])->name('users.syncRoles');
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
    Route::post('pass/{user}', [UserController::class,'password_rec'])->name('recuperar.contrasena');
    Route::get('createlogo/{user}', [UserController::class,'logo_create'])->name('create.logo');
    Route::post('updatelogo/{user}', [UserController::class,'logo_update'])->name('update.logo');
    Route::post('deletelogo/{user}', [UserController::class,'logo_delete'])->name('delete.logo');

    Route::resource('roles', App\Http\Controllers\RoleController::class)->names('roles');
    Route::get('roles/{role}/assign-permissions', [App\Http\Controllers\RoleController::class, 'assignPermissions'])->name('roles.assignPermissions');
    Route::post('roles/{role}/sync-permissions', [App\Http\Controllers\RoleController::class, 'syncPermissions'])->name('roles.syncPermissions');
    Route::resource('permissions', App\Http\Controllers\PermissionController::class)->names('permissions');
    Route::resource('photo-types', App\Http\Controllers\PhotoTypeController::class)->names('photo-types');

    Route::resource('producers', App\Http\Controllers\ProducerController::class)->names('producers');

    Route::resource('telefonos', App\Http\Controllers\TelefonoController::class)->only(['store', 'update', 'destroy'])->names('telefonos');

    Route::resource('recepciones', App\Http\Controllers\RecepcionController::class)->only(['index'])->names('recepciones');

    Route::resource('procesos', App\Http\Controllers\ProcesoController::class)->only(['index'])->names('procesos');

    Route::resource('control-calidad', App\Http\Controllers\ControlCalidadController::class)->only(['index'])->names('control-calidad');
    Route::get('control-calidad/get-valores', [App\Http\Controllers\ControlCalidadController::class, 'getValores'])->name('control-calidad.get-valores');
    Route::post('control-calidad/store-calidad', [App\Http\Controllers\ControlCalidadController::class, 'storeCalidad'])->name('control-calidad.store-calidad');
    Route::post('control-calidad/store-detalle', [App\Http\Controllers\ControlCalidadController::class, 'storeDetalle'])->name('control-calidad.store-detalle');
    Route::get('control-calidad/{recepcion}/detalles', [App\Http\Controllers\ControlCalidadController::class, 'getDetalles'])->name('control-calidad.get-detalles');
    Route::get('control-calidad/{recepcion}/calidad', [App\Http\Controllers\ControlCalidadController::class, 'getCalidad'])->name('control-calidad.get-calidad');
    Route::post('control-calidad/{recepcion}/cargar-firmpro', [App\Http\Controllers\ControlCalidadController::class, 'cargarFirmpro'])->name('control-calidad.cargar-firmpro');
    Route::get('control-calidad/{recepcion}/report', [App\Http\Controllers\ControlCalidadController::class, 'generateReport'])->name('control-calidad.generate-report');

    // Processed Fruit Quality Control Routes
    Route::get('processed-fruit-quality', [App\Http\Controllers\ProcessedFruitQualityController::class, 'index'])->name('processed-fruit-quality.index');
    Route::post('processed-fruit-quality/quality', [App\Http\Controllers\ProcessedFruitQualityController::class, 'storeQuality'])->name('processed-fruit-quality.storeQuality');
    Route::post('processed-fruit-quality/detail', [App\Http\Controllers\ProcessedFruitQualityController::class, 'storeDetail'])->name('processed-fruit-quality.storeDetail');
    Route::get('processed-fruit-quality/{proceso}/quality', [App\Http\Controllers\ProcessedFruitQualityController::class, 'getQuality'])->name('processed-fruit-quality.getQuality');
    Route::get('processed-fruit-quality/{proceso}/details', [App\Http\Controllers\ProcessedFruitQualityController::class, 'getDetails'])->name('processed-fruit-quality.getDetails');

    Route::post('producers/{producer}/agronomists', [App\Http\Controllers\ProducerAgronomistController::class, 'store'])->name('producers.agronomists.store');
    Route::delete('producers/{producer}/agronomists', [App\Http\Controllers\ProducerAgronomistController::class, 'destroy'])->name('producers.agronomists.destroy');
    Route::get('agronomists', [App\Http\Controllers\ProducerAgronomistController::class, 'index'])->name('agronomists.index');

    Route::resource('calidad', App\Http\Controllers\CalidadController::class);
    Route::post('calidad/{calidad}/detalles', [App\Http\Controllers\DetalleController::class, 'store'])->name('calidad.detalles.store');

    // Photo Upload Routes
    Route::post('calidad/{calidad}/photos', [App\Http\Controllers\QualityControlPhotoController::class, 'store'])->name('quality-control-photos.store');
    Route::delete('quality-control-photos/{photo}', [App\Http\Controllers\QualityControlPhotoController::class, 'destroy'])->name('quality-control-photos.destroy');

    // Service Module Routes
    Route::resource('services', App\Http\Controllers\ServiceController::class)->names('services');
    Route::post('services/{service}/attach-user', [App\Http\Controllers\ServiceController::class, 'attachUser'])->name('services.attachUser');
    Route::delete('services/{service}/detach-user/{user}', [App\Http\Controllers\ServiceController::class, 'detachUser'])->name('services.detachUser');
    Route::get('services/{service}/producers', [App\Http\Controllers\ServiceController::class, 'getServiceProducers'])->name('services.getServiceProducers');

    // Documentation Module Routes
    Route::resource('continents', App\Http\Controllers\ContinentController::class)->names('continents');
    Route::resource('countries', App\Http\Controllers\CountryController::class)->names('countries');
    Route::resource('authorization-types', App\Http\Controllers\AuthorizationTypeController::class)->names('authorization-types');
    Route::resource('certifying-houses', App\Http\Controllers\CertifyingHouseController::class)->names('certifying-houses');
    Route::resource('certificate-types', App\Http\Controllers\CertificateTypeController::class)->names('certificate-types');
    Route::resource('producer-certifications', App\Http\Controllers\ProducerCertificationController::class)->names('producer-certifications');
    Route::get('producer-certifications/{producer}/show', [App\Http\Controllers\ProducerCertificationController::class, 'show'])->name('producer-certifications.show');
    Route::get('producer-certifications/{document}/download', [App\Http\Controllers\ProducerCertificationController::class, 'downloadOtherDocument'])->name('producer-certifications.downloadOtherDocument');
    Route::get('especies/{especie}/variedades', [App\Http\Controllers\MarketController::class, 'getVariedadesByEspecie'])->name('especies.variedades');
    Route::get('especies/{especie}/markets', [App\Http\Controllers\MarketController::class, 'getMarketsByEspecie'])->name('especies.markets'); // New route
    Route::resource('markets', App\Http\Controllers\MarketController::class)->names('markets');

    // SAG Module Routes
    Route::get('sag', [App\Http\Controllers\SagController::class, 'index'])->name('sag.index');
    Route::get('sag/{rut}', [App\Http\Controllers\SagController::class, 'show'])->name('sag.show');
    Route::post('sag/update-country-status', [App\Http\Controllers\SagController::class, 'updateCountryAuthorizationStatus'])->name('sag.updateCountryStatus'); // New route
    Route::post('sag/certifications/upload', [App\Http\Controllers\SagController::class, 'uploadCertification'])->name('sag.certifications.upload'); // New route
    Route::get('sag/certifications/{certification}/download', [App\Http\Controllers\SagController::class, 'downloadCertification'])->name('sag.certifications.download'); // New route

    Route::get('/mrl-samples', [App\Http\Controllers\MrlSampleController::class, 'index'])->name('mrl-samples.index');
    Route::get('/mrl-samples/create', [App\Http\Controllers\MrlSampleController::class, 'create'])->name('mrl-samples.create');
    Route::post('/mrl-samples', [App\Http\Controllers\MrlSampleController::class, 'store'])->name('mrl-samples.store');

    Route::get('/api/variedades-by-especie/{especieId}', [App\Http\Controllers\MrlSampleController::class, 'getVariedadesByEspecie'])->name('api.variedades-by-especie');
    Route::get('/api/csgs-by-rut/{rut}', [App\Http\Controllers\MrlSampleController::class, 'getCsgsByRut'])->name('api.csgs-by-rut');

    Route::resource('contracts', App\Http\Controllers\ContractController::class)->names('contracts');
});

require __DIR__.'/auth.php';
