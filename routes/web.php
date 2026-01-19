<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReporteriaController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Models\Service;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MassCommunicationController;
use App\Http\Controllers\CommercialDiscardController;
use App\Http\Controllers\NotificationLogController;
use App\Http\Controllers\FieldVisitController;
use App\Http\Controllers\FieldManagementController;
use App\Http\Controllers\ValidacionesController;
use App\Http\Controllers\SystemLogController;
use App\Http\Controllers\ProspectoProductorController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::view('/privacy', 'privacy')->name('privacy');
Route::get('control-calidad/exportable-percentages', [App\Http\Controllers\ControlCalidadController::class, 'exportablePercentages'])->name('control-calidad.exportable-percentages');
Route::get('/dashboard', function () {
    $user = auth()->user();
    if (! $user) {
        return redirect()->route('login');
    }
    // Admin: puede ver todos los dashboards → envíalo al índice de servicios
    if (method_exists($user, 'hasRole') && ($user->hasRole('Admin') || $user->hasRole('Administrador'))) {
        return redirect()->route('admin.dashboard');
    }
    // Dueño de servicio: su dashboard como página principal
    $service = Service::where('owner_id', $user->id)->first();
    if ($service) {
        return redirect()->route('services.dashboard', $service);
    }
    // Por defecto dashboard genérico
    return app(DashboardController::class)(request());
})->middleware(['auth', 'verified'])->name('dashboard');


Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])->middleware(['auth','verified'])->name('admin.dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('users', UserController::class)->names('users');
    Route::get('users/{user}/assign-roles', [UserController::class, 'assignRoles'])->name('users.assignRoles');
    Route::post('users/{user}/sync-roles', [UserController::class, 'syncRoles'])->name('users.syncRoles');
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
    Route::post('pass/{user}', [UserController::class, 'password_rec'])->name('recuperar.contrasena');
    Route::get('createlogo/{user}', [UserController::class, 'logo_create'])->name('create.logo');
    Route::post('updatelogo/{user}', [UserController::class, 'logo_update'])->name('update.logo');
    Route::post('deletelogo/{user}', [UserController::class, 'logo_delete'])->name('delete.logo');

    Route::resource('roles', App\Http\Controllers\RoleController::class)->names('roles');
    Route::get('roles/{role}/assign-permissions', [App\Http\Controllers\RoleController::class, 'assignPermissions'])->name('roles.assignPermissions');
    Route::post('roles/{role}/sync-permissions', [App\Http\Controllers\RoleController::class, 'syncPermissions'])->name('roles.syncPermissions');
    Route::resource('permissions', App\Http\Controllers\PermissionController::class)->names('permissions');
    Route::resource('photo-types', App\Http\Controllers\PhotoTypeController::class)->names('photo-types');
    Route::get('photo-types-all', [App\Http\Controllers\PhotoTypeController::class, 'all'])->name('photo-types.all');
    Route::resource('valores', App\Http\Controllers\ValorController::class)
        ->parameters(['valores' => 'valor'])
        ->names('valores');

Route::resource('producers', App\Http\Controllers\ProducerController::class)->names('producers');
Route::post('producers/sync-active', [App\Http\Controllers\ProducerController::class, 'syncActive'])->name('producers.sync-active');
Route::get('producers/{producer}/dashboard', [App\Http\Controllers\ProducerController::class, 'dashboard'])->name('producers.dashboard');
Route::post('producers/{producer}/welcome-email', [App\Http\Controllers\ProducerController::class, 'sendWelcomeEmail'])->name('producers.welcome-email');

    Route::resource('telefonos', App\Http\Controllers\TelefonoController::class)->only(['store', 'update', 'destroy'])->names('telefonos');

    Route::get('admin/mass-communications', [MassCommunicationController::class, 'create'])->name('mass-communications.create');
    Route::post('admin/mass-communications', [MassCommunicationController::class, 'store'])->name('mass-communications.store');

    Route::resource('recepciones', App\Http\Controllers\RecepcionController::class)->only(['index'])->names('recepciones');
    Route::post('recepciones/sync', [App\Http\Controllers\RecepcionController::class, 'recepction_sync'])->name('recepciones.sync');

    Route::resource('procesos', App\Http\Controllers\ProcesoController::class)->only(['index'])->names('procesos');
    Route::post('procesos/informes/upload', [App\Http\Controllers\ProcesoController::class, 'uploadInformes'])->name('procesos.informes.upload');
    Route::post('procesos/{proceso}/resend-report', [App\Http\Controllers\ProcesoController::class, 'resendReport'])->name('procesos.resend-report');
    Route::post('procesos/{proceso}/sync-exportadora', [App\Http\Controllers\ProcesoController::class, 'syncExportadora'])->name('procesos.sync-exportadora');
    Route::post('procesos/sync-exportadora', [App\Http\Controllers\ProcesoController::class, 'syncExportadoraAll'])->name('procesos.sync-exportadora-all');
    Route::post('procesos/sync', [App\Http\Controllers\ProcesoController::class, 'procesos_sync'])->name('procesos.sync');
    Route::post('procesos/sync-lpp', [App\Http\Controllers\ProcesoController::class, 'sync_lpp'])->name('procesos.sync-lpp');

    Route::resource('control-calidad', App\Http\Controllers\ControlCalidadController::class)->only(['index'])->names('control-calidad');
    Route::get('control-calidad/get-valores', [App\Http\Controllers\ControlCalidadController::class, 'getValores'])->name('control-calidad.get-valores');
    Route::post('control-calidad/store-calidad', [App\Http\Controllers\ControlCalidadController::class, 'storeCalidad'])->name('control-calidad.store-calidad');
    Route::post('control-calidad/store-detalle', [App\Http\Controllers\ControlCalidadController::class, 'storeDetalle'])->name('control-calidad.store-detalle');
    Route::delete('control-calidad/detalles/{detalle}', [App\Http\Controllers\ControlCalidadController::class, 'destroyDetalle'])->name('control-calidad.destroy-detalle');
    Route::post('control-calidad/sync-notas-calidad', [App\Http\Controllers\ControlCalidadController::class, 'syncNotasCalidad'])->name('control-calidad.sync-notas');

    Route::get('control-calidad/descarte-comercial', [CommercialDiscardController::class, 'index'])->name('commercial-discards.index');
    Route::get('control-calidad/descarte-comercial/crear', [CommercialDiscardController::class, 'create'])->name('commercial-discards.create');
    Route::post('control-calidad/descarte-comercial', [CommercialDiscardController::class, 'store'])->name('commercial-discards.store');
    Route::get('control-calidad/descarte-comercial/{commercialDiscard}/pdf', [CommercialDiscardController::class, 'pdf'])->name('commercial-discards.pdf');
    Route::get('control-calidad/descarte-comercial/process/{n_proceso}', [CommercialDiscardController::class, 'lookupProcess'])->name('commercial-discards.lookup-process');
    Route::get('control-calidad/{recepcion}/detalles', [App\Http\Controllers\ControlCalidadController::class, 'getDetalles'])->name('control-calidad.get-detalles');
    Route::get('control-calidad/{recepcion}/calidad', [App\Http\Controllers\ControlCalidadController::class, 'getCalidad'])->name('control-calidad.get-calidad');
    Route::get('control-calidad/{recepcion}/validate-kilos', [App\Http\Controllers\ControlCalidadController::class, 'validateKilos'])->name('control-calidad.validate-kilos');
    Route::post('control-calidad/{recepcion}/cargar-firmpro', [App\Http\Controllers\ControlCalidadController::class, 'cargarFirmpro'])->name('control-calidad.cargar-firmpro');
    Route::get('control-calidad/{recepcion}/report', [App\Http\Controllers\ControlCalidadController::class, 'generateReport'])->name('control-calidad.generate-report');
    // React preview page with shadcn switch
    Route::get('control-calidad/{recepcion}/report/preview', [App\Http\Controllers\ControlCalidadController::class, 'previewPage'])->name('control-calidad.preview-report');
    // HTML-only report for iframe rendering inside preview page
    Route::get('control-calidad/{recepcion}/report/preview/html', [App\Http\Controllers\ControlCalidadController::class, 'previewReport'])->name('control-calidad.preview-report-html');
    Route::post('control-calidad/{recepcion}/report/send-preview', [App\Http\Controllers\ControlCalidadController::class, 'sendPreviewEmail'])->name('control-calidad.send-preview');
    Route::post('control-calidad/{recepcion}/report/send-preview-whatsapp', [App\Http\Controllers\ControlCalidadController::class, 'sendPreviewWhatsapp'])->name('control-calidad.send-preview-whatsapp');
    Route::post('control-calidad/{recepcion}/report/approve', [App\Http\Controllers\ControlCalidadController::class, 'approveReport'])->name('control-calidad.approve-report');
    Route::post('control-calidad/{recepcion}/report/resend', [App\Http\Controllers\ControlCalidadController::class, 'resendReport'])->name('control-calidad.resend-report');
    Route::get('admin/notification-logs', [NotificationLogController::class, 'index'])->name('notification-logs.index');
    Route::get('admin/notification-logs/export', [NotificationLogController::class, 'export'])->name('notification-logs.export');
    Route::get('admin/system-logs', [SystemLogController::class, 'index'])->name('system-logs.index');
    Route::get('admin/prospectos-productores/create', [ProspectoProductorController::class, 'create'])->name('prospectos-productores.create');
    Route::post('admin/prospectos-productores', [ProspectoProductorController::class, 'store'])->name('prospectos-productores.store');
    Route::get('field-visits', [FieldVisitController::class, 'index'])->name('field-visits.index');
    Route::post('field-visits', [FieldVisitController::class, 'store'])->name('field-visits.store');

    // Field Management Module (Contratistas, cuadrillas, trabajadores, credenciales, campos)
    Route::prefix('field-management')->name('field-management.')->group(function () {
        Route::get('/', [FieldManagementController::class, 'index'])->name('index');
        Route::get('/sync', [FieldManagementController::class, 'apiSync'])->name('sync');

        Route::post('/contractors', [FieldManagementController::class, 'storeContractor'])->name('contractors.store');
        Route::put('/contractors/{contractor}', [FieldManagementController::class, 'updateContractor'])->name('contractors.update');
        Route::delete('/contractors/{contractor}', [FieldManagementController::class, 'destroyContractor'])->name('contractors.destroy');

        Route::post('/crews', [FieldManagementController::class, 'storeCrew'])->name('crews.store');
        Route::put('/crews/{crew}', [FieldManagementController::class, 'updateCrew'])->name('crews.update');
        Route::delete('/crews/{crew}', [FieldManagementController::class, 'destroyCrew'])->name('crews.destroy');

        Route::post('/workers', [FieldManagementController::class, 'storeWorker'])->name('workers.store');
        Route::put('/workers/{worker}', [FieldManagementController::class, 'updateWorker'])->name('workers.update');
        Route::delete('/workers/{worker}', [FieldManagementController::class, 'destroyWorker'])->name('workers.destroy');

        Route::post('/credentials', [FieldManagementController::class, 'storeCredential'])->name('credentials.store');
        Route::put('/credentials/{credential}', [FieldManagementController::class, 'updateCredential'])->name('credentials.update');
        Route::delete('/credentials/{credential}', [FieldManagementController::class, 'destroyCredential'])->name('credentials.destroy');
        Route::post('/credentials/assign', [FieldManagementController::class, 'assignCredential'])->name('credentials.assign');

        Route::post('/fields', [FieldManagementController::class, 'storeField'])->name('fields.store');
        Route::put('/fields/{field}', [FieldManagementController::class, 'updateField'])->name('fields.update');
        Route::delete('/fields/{field}', [FieldManagementController::class, 'destroyField'])->name('fields.destroy');

        Route::post('/blocks', [FieldManagementController::class, 'storeBlock'])->name('blocks.store');
        Route::put('/blocks/{block}', [FieldManagementController::class, 'updateBlock'])->name('blocks.update');
        Route::delete('/blocks/{block}', [FieldManagementController::class, 'destroyBlock'])->name('blocks.destroy');

        Route::post('/fruit-configs', [FieldManagementController::class, 'storeFruitConfig'])->name('fruit-configs.store');
        Route::put('/fruit-configs/{fruitConfig}', [FieldManagementController::class, 'updateFruitConfig'])->name('fruit-configs.update');
        Route::delete('/fruit-configs/{fruitConfig}', [FieldManagementController::class, 'destroyFruitConfig'])->name('fruit-configs.destroy');
    });

    // Processed Fruit Quality Control Routes
    Route::get('processed-fruit-quality', [App\Http\Controllers\ProcessedFruitQualityController::class, 'index'])->name('processed-fruit-quality.index');
    Route::post('processed-fruit-quality/quality', [App\Http\Controllers\ProcessedFruitQualityController::class, 'storeQuality'])->name('processed-fruit-quality.storeQuality');
    Route::put('processed-fruit-quality/quality/{quality}', [App\Http\Controllers\ProcessedFruitQualityController::class, 'updateQuality'])->name('processed-fruit-quality.updateQuality');
    Route::post('processed-fruit-quality/detail', [App\Http\Controllers\ProcessedFruitQualityController::class, 'storeDetail'])->name('processed-fruit-quality.storeDetail');
    Route::patch('processed-fruit-quality/{quality}/tolerance', [App\Http\Controllers\ProcessedFruitQualityController::class, 'updateToleranceLabel'])->name('processed-fruit-quality.updateTolerance');
    Route::patch('processed-fruit-quality/{quality}/status', [App\Http\Controllers\ProcessedFruitQualityController::class, 'updateStatus'])->name('processed-fruit-quality.updateStatus');
    Route::get('processed-fruit-quality/{proceso}/quality', [App\Http\Controllers\ProcessedFruitQualityController::class, 'getQuality'])->name('processed-fruit-quality.getQuality');
    Route::get('processed-fruit-quality/{proceso}/details', [App\Http\Controllers\ProcessedFruitQualityController::class, 'getDetails'])->name('processed-fruit-quality.getDetails');
    Route::get('processed-fruit-quality/export', [App\Http\Controllers\ProcessedFruitQualityController::class, 'export'])->name('processed-fruit-quality.export');
    Route::get('processed-fruit-quality/{proceso}/report', [App\Http\Controllers\ProcessedFruitQualityController::class, 'generateReport'])->name('processed-fruit-quality.generate-report');
    Route::get('processed-fruit-quality/{proceso}/report/preview', [App\Http\Controllers\ProcessedFruitQualityController::class, 'previewReport'])->name('processed-fruit-quality.preview-report');
    Route::get('processed-fruit-quality/{proceso}/report/consolidated', [App\Http\Controllers\ProcessedFruitQualityController::class, 'consolidatedReport'])->name('processed-fruit-quality.consolidated-report');

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
    Route::get('services/{service}/dashboard', [App\Http\Controllers\ServiceController::class, 'dashboard'])->name('services.dashboard');
    Route::post('services/{service}/attach-user', [App\Http\Controllers\ServiceController::class, 'attachUser'])->name('services.attachUser');
    Route::delete('services/{service}/detach-user/{user}', [App\Http\Controllers\ServiceController::class, 'detachUser'])->name('services.detachUser');
    Route::get('services/{service}/producers', [App\Http\Controllers\ServiceController::class, 'getServiceProducers'])->name('services.getServiceProducers');

    // Documentation Module Routes
    Route::resource('continents', App\Http\Controllers\ContinentController::class)->names('continents');
    Route::resource('countries', App\Http\Controllers\CountryController::class)->names('countries');
    Route::resource('authorization-types', App\Http\Controllers\AuthorizationTypeController::class)->names('authorization-types');
    Route::resource('certifying-houses', App\Http\Controllers\CertifyingHouseController::class)->names('certifying-houses');
    Route::resource('certificate-types', App\Http\Controllers\CertificateTypeController::class)->names('certificate-types');
    Route::resource('producer-certifications', App\Http\Controllers\ProducerCertificationController::class)->except(['update'])->names('producer-certifications');
    Route::post('producer-certifications/{certification}', [App\Http\Controllers\ProducerCertificationController::class, 'update'])->name('producer-certifications.update');
    Route::get('producer-certifications/{producer}/show', [App\Http\Controllers\ProducerCertificationController::class, 'show'])->name('producer-certifications.show');
    Route::get('producer-certifications/{document}/download', [App\Http\Controllers\ProducerCertificationController::class, 'downloadOtherDocument'])->name('producer-certifications.downloadOtherDocument');
    Route::get('especies/{especie}/variedades', [App\Http\Controllers\MarketController::class, 'getVariedadesByEspecie'])->name('especies.variedades');
    Route::get('especies/{especie}/markets', [App\Http\Controllers\MarketController::class, 'getMarketsByEspecie'])->name('especies.markets'); // New route
    Route::resource('markets', App\Http\Controllers\MarketController::class)->names('markets');

    // SAG Module Routes
    Route::get('sag', [App\Http\Controllers\SagController::class, 'index'])->name('sag.index');
    Route::get('sag/export', [App\Http\Controllers\SagController::class, 'export'])->name('sag.export');
    Route::get('sag/{rut}', [App\Http\Controllers\SagController::class, 'show'])->name('sag.show');
    Route::post('sag/update-country-status', [App\Http\Controllers\SagController::class, 'updateCountryAuthorizationStatus'])->name('sag.updateCountryStatus'); // New route
    Route::post('sag/certifications/upload', [App\Http\Controllers\SagController::class, 'uploadCertification'])->name('sag.certifications.upload'); // New route
    Route::get('sag/certifications/{certification}/download', [App\Http\Controllers\SagController::class, 'downloadCertification'])->name('sag.certifications.download'); // New route
    Route::delete('sag/certifications/{certification}', [App\Http\Controllers\SagController::class, 'destroyCertification'])->name('sag.certifications.destroy');
    Route::post('sag/certifications/{certification}/active', [App\Http\Controllers\SagController::class, 'setCertificationActive'])->name('sag.certifications.setActive');

    // SDP Sites (Sitios de Plantación) Maintainer
    Route::resource('sdp-sites', App\Http\Controllers\SdpSiteController::class)->names('sdp-sites');

    Route::get('/mrl-samples', [App\Http\Controllers\MrlSampleController::class, 'index'])->name('mrl-samples.index');
    Route::get('/mrl-samples/create', [App\Http\Controllers\MrlSampleController::class, 'create'])->name('mrl-samples.create');
    Route::post('/mrl-samples', [App\Http\Controllers\MrlSampleController::class, 'store'])->name('mrl-samples.store');

    Route::get('/api/variedades-by-especie/{especieId}', [App\Http\Controllers\MrlSampleController::class, 'getVariedadesByEspecie'])->name('api.variedades-by-especie');
    Route::get('/api/csgs-by-rut/{rut}', [App\Http\Controllers\MrlSampleController::class, 'getCsgsByRut'])->name('api.csgs-by-rut');

    Route::resource('contracts', App\Http\Controllers\ContractController::class)->names('contracts');
    Route::get('validaciones/recepciones-sin-contrato', [ValidacionesController::class, 'recepcionesSinContrato'])->name('validaciones.recepciones-sin-contrato');

    Route::get('reporteria/calidad', [ReporteriaController::class, 'index'])->name('reporteria.calidad');
    Route::get('reporteria/export-consolidated', [ReporteriaController::class, 'exportConsolidated'])->name('reporteria.export.consolidated');

    Route::get('login-activity', [App\Http\Controllers\LoginActivityController::class, 'index'])->name('login-activity.index');

    // Weekly Harvest Estimates
    Route::resource('weekly-harvest-estimates', App\Http\Controllers\WeeklyHarvestEstimateController::class)->names('weekly-harvest-estimates');
    Route::post('weekly-harvest-estimates/import', [App\Http\Controllers\WeeklyHarvestEstimateController::class, 'import'])->name('weekly-harvest-estimates.import');
    Route::get('/api/producers/{producer}/agronomists', [App\Http\Controllers\WeeklyHarvestEstimateController::class, 'getProducerAgronomists'])->name('api.producer-agronomists');

    // Producer Groups
    Route::resource('producer-groups', App\Http\Controllers\ProducerGroupController::class)->except(['show', 'create'])->names('producer-groups');
});

require __DIR__.'/auth.php';
