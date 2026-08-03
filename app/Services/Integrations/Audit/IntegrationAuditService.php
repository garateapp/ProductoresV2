<?php

namespace App\Services\Integrations\Audit;

use App\Models\IntegrationAuditLog;

class IntegrationAuditService
{
    public function log(
        string $evento,
        string $entidadTipo,
        int $entidadId,
        ?string $entidadNombre = null,
        ?array $valoresPrevios = null,
        ?array $valoresNuevos = null,
        ?string $motivo = null,
        ?int $runId = null,
    ): void {
        IntegrationAuditLog::create([
            'user_id' => auth()->id(),
            'evento' => $evento,
            'entidad_tipo' => $entidadTipo,
            'entidad_id' => $entidadId,
            'entidad_nombre' => $entidadNombre,
            'valores_previos' => $valoresPrevios,
            'valores_nuevos' => $valoresNuevos,
            'ip_address' => request()->ip(),
            'motivo' => $motivo,
            'run_id' => $runId,
        ]);
    }

    public function profileCreated(int $profileId, string $nombre, ?string $codigo): void
    {
        $this->log('profile_created', 'integration_profile', $profileId, "[{$codigo}] {$nombre}");
    }

    public function profileUpdated(int $profileId, string $nombre, array $old, array $new): void
    {
        $this->log('profile_updated', 'integration_profile', $profileId, $nombre, $old, $new);
    }

    public function profilePublished(int $profileId, string $nombre, int $version): void
    {
        $this->log('profile_published', 'integration_profile', $profileId, "{$nombre} v{$version}", motivo: "Publicación versión {$version}");
    }

    public function profileDuplicated(int $profileId, string $nombre, int $newProfileId): void
    {
        $this->log('profile_duplicated', 'integration_profile', $profileId, $nombre, valoresNuevos: ['duplicated_to' => $newProfileId]);
    }

    public function profileStatusChanged(int $profileId, string $nombre, string $oldStatus, string $newStatus): void
    {
        $this->log('profile_status_changed', 'integration_profile', $profileId, $nombre, ['estado' => $oldStatus], ['estado' => $newStatus]);
    }

    public function mappingSetCreated(int $mappingSetId, string $nombre): void
    {
        $this->log('mapping_set_created', 'integration_mapping_set', $mappingSetId, $nombre);
    }

    public function mappingItemCreated(int $mappingSetId, string $nombre, array $inputValues): void
    {
        $this->log('mapping_item_created', 'integration_mapping_set', $mappingSetId, $nombre, valoresNuevos: $inputValues);
    }

    public function runExecuted(int $runId, int $profileId, ?string $profileName): void
    {
        $this->log('run_executed', 'integration_run', $runId, $profileName, runId: $runId);
    }

    public function runCancelled(int $runId, int $profileId, ?string $profileName): void
    {
        $this->log('run_cancelled', 'integration_run', $runId, $profileName, runId: $runId);
    }

    public function runReprocessed(int $runId, int $profileId, ?string $profileName): void
    {
        $this->log('run_reprocessed', 'integration_run', $runId, $profileName, runId: $runId);
    }

    public function pendingResolved(int $pendingId, string $campo, string $valor): void
    {
        $this->log('pending_resolved', 'integration_pending_mapping', $pendingId, "{$campo}: {$valor}");
    }
}
