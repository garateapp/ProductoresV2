<?php

use App\Http\Controllers\Integrations\ClientController;
use App\Http\Controllers\Integrations\DashboardController;
use App\Http\Controllers\Integrations\ProfileController;
use App\Http\Controllers\Integrations\InputFieldController;
use App\Http\Controllers\Integrations\OutputFieldController;
use App\Http\Controllers\Integrations\VersionDiffController;
use App\Http\Controllers\Integrations\RunController;
use App\Http\Controllers\Integrations\RunRecordController;
use App\Http\Controllers\Integrations\SimulatorController;
use App\Http\Controllers\Integrations\SourceAdapterController;
use App\Http\Controllers\Integrations\PendingMappingController;
use App\Http\Controllers\Integrations\FailureController;
use App\Http\Controllers\Integrations\AuditController;
use App\Http\Controllers\Integrations\ExportController;
use App\Http\Controllers\Integrations\RuleController;
use App\Http\Controllers\Integrations\MappingSetController;
use App\Http\Controllers\Integrations\MappingItemController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('integrations')->name('integrations.')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('clients', ClientController::class)->except(['show'])->parameters(['clients' => 'client']);
    Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');

    Route::resource('source-adapters', SourceAdapterController::class)->except(['show'])->parameters(['source-adapters' => 'sourceAdapter']);
    Route::get('source-adapters/{sourceAdapter}', [SourceAdapterController::class, 'show'])->name('source-adapters.show');

    Route::get('profiles', [ProfileController::class, 'index'])->name('profiles.index');
    Route::get('profiles/create', [ProfileController::class, 'create'])->name('profiles.create');
    Route::post('profiles', [ProfileController::class, 'store'])->name('profiles.store');
    Route::get('profiles/{profile}', [ProfileController::class, 'show'])->name('profiles.show');
    Route::get('profiles/{profile}/edit', [ProfileController::class, 'edit'])->name('profiles.edit');
    Route::put('profiles/{profile}', [ProfileController::class, 'update'])->name('profiles.update');
    Route::post('profiles/{profile}/duplicate', [ProfileController::class, 'duplicate'])->name('profiles.duplicate');
    Route::post('profiles/{profile}/publish', [ProfileController::class, 'publish'])->name('profiles.publish');
    Route::post('profiles/{profile}/toggle-active', [ProfileController::class, 'toggleActive'])->name('profiles.toggle-active');

    Route::post('profiles/{profile}/input-fields', [InputFieldController::class, 'store'])->name('input-fields.store');
    Route::put('profiles/{profile}/input-fields/{inputField}', [InputFieldController::class, 'update'])->name('input-fields.update');
    Route::delete('profiles/{profile}/input-fields/{inputField}', [InputFieldController::class, 'destroy'])->name('input-fields.destroy');
    Route::post('profiles/{profile}/input-fields/reorder', [InputFieldController::class, 'reorder'])->name('input-fields.reorder');

    Route::post('profiles/{profile}/output-fields', [OutputFieldController::class, 'store'])->name('output-fields.store');
    Route::put('profiles/{profile}/output-fields/{outputField}', [OutputFieldController::class, 'update'])->name('output-fields.update');
    Route::delete('profiles/{profile}/output-fields/{outputField}', [OutputFieldController::class, 'destroy'])->name('output-fields.destroy');
    Route::post('profiles/{profile}/output-fields/reorder', [OutputFieldController::class, 'reorder'])->name('output-fields.reorder');

    Route::post('profiles/{profile}/rules', [RuleController::class, 'store'])->name('rules.store');
    Route::put('profiles/{profile}/rules/{rule}', [RuleController::class, 'update'])->name('rules.update');
    Route::delete('profiles/{profile}/rules/{rule}', [RuleController::class, 'destroy'])->name('rules.destroy');
    Route::post('profiles/{profile}/rules/reorder', [RuleController::class, 'reorder'])->name('rules.reorder');

    Route::get('mapping-sets', [MappingSetController::class, 'index'])->name('mapping-sets.index');
    Route::post('mapping-sets', [MappingSetController::class, 'store'])->name('mapping-sets.store');
    Route::get('mapping-sets/{mappingSet}', [MappingSetController::class, 'show'])->name('mapping-sets.show');
    Route::post('mapping-sets/{mappingSet}/publish', [MappingSetController::class, 'publish'])->name('mapping-sets.publish');

    Route::post('mapping-sets/{mappingSet}/items', [MappingItemController::class, 'store'])->name('mapping-items.store');
    Route::put('mapping-sets/{mappingSet}/items/{item}', [MappingItemController::class, 'update'])->name('mapping-items.update');
    Route::delete('mapping-sets/{mappingSet}/items/{item}', [MappingItemController::class, 'destroy'])->name('mapping-items.destroy');
    Route::post('mapping-sets/{mappingSet}/items/import', [MappingItemController::class, 'importBulk'])->name('mapping-items.import');

    Route::get('compare', [VersionDiffController::class, 'index'])->name('compare.index');
    Route::get('compare/versions/{version}', [VersionDiffController::class, 'show'])->name('compare.show');
    Route::get('compare/versions/{version}/diff', [VersionDiffController::class, 'diff'])->name('compare.diff');

    Route::get('runs', [RunController::class, 'index'])->name('runs.index');
    Route::post('runs', [RunController::class, 'store'])->name('runs.store');
    Route::get('runs/{run}', [RunController::class, 'show'])->name('runs.show');
    Route::post('runs/{run}/cancel', [RunController::class, 'cancel'])->name('runs.cancel');
    Route::post('runs/{run}/reprocess', [RunController::class, 'reprocess'])->name('runs.reprocess');

    Route::get('runs/{run}/records', [RunRecordController::class, 'index'])->name('run-records.index');
    Route::get('runs/{run}/records/{record}', [RunRecordController::class, 'show'])->name('run-records.show');

    Route::get('simulator', [SimulatorController::class, 'index'])->name('simulator.index');
    Route::post('simulator/preview', [SimulatorController::class, 'preview'])->name('simulator.preview');

    Route::get('pending-mappings', [PendingMappingController::class, 'index'])->name('pending-mappings.index');
    Route::put('pending-mappings/{pendingMapping}', [PendingMappingController::class, 'update'])->name('pending-mappings.update');
    Route::post('pending-mappings/bulk-resolve', [PendingMappingController::class, 'bulkResolve'])->name('pending-mappings.bulk-resolve');

    Route::get('failures', [FailureController::class, 'index'])->name('failures.index');
    Route::get('failures/{record}', [FailureController::class, 'show'])->name('failures.show');
    Route::post('failures/{record}/reprocess', [FailureController::class, 'reprocess'])->name('failures.reprocess');

    Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
    Route::get('audit/{auditLog}', [AuditController::class, 'show'])->name('audit.show');

    Route::get('exports', [ExportController::class, 'index'])->name('exports.index');
    Route::get('exports/{export}/download', [ExportController::class, 'download'])->name('exports.download');
});
