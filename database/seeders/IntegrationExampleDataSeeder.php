<?php

namespace Database\Seeders;

use App\Enums\IntegrationFieldType;
use App\Enums\IntegrationProfileStatus;
use App\Enums\IntegrationRuleErrorPolicy;
use App\Enums\IntegrationRuleType;
use App\Models\IntegrationClient;
use App\Models\IntegrationInputField;
use App\Models\IntegrationOutputField;
use App\Models\IntegrationProfile;
use App\Models\IntegrationProfileVersion;
use App\Models\IntegrationRule;
use App\Models\IntegrationRuleInput;
use App\Models\IntegrationRuleOutput;
use App\Models\User;
use Illuminate\Database\Seeder;

class IntegrationExampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::whereIn('name', ['Admin', 'Administrador'])->first() ?? User::first();

        if (!$admin) {
            $this->command->warn('No se encontró un usuario administrador. Ejecute primero un seeder de usuarios.');
            return;
        }

        $client = IntegrationClient::firstOrCreate(
            ['codigo' => 'SAP-001'],
            [
                'nombre' => 'SAP Santiago',
                'descripcion' => 'Cliente SAP para integración de recepciones',
                'activo' => true,
                'metadata' => ['endpoint' => 'https://sap.example.com/api', 'timeout' => 30],
            ]
        );

        $client2 = IntegrationClient::firstOrCreate(
            ['codigo' => 'API-001'],
            [
                'nombre' => 'API Clientes',
                'descripcion' => 'API REST para exportación de datos',
                'activo' => true,
                'metadata' => ['endpoint' => 'https://api.example.com/v1', 'timeout' => 60],
            ]
        );

        $profile = IntegrationProfile::create([
            'client_id' => $client->id,
            'codigo' => 'INT-REC-001',
            'nombre' => 'Integración Recepciones SAP',
            'descripcion' => 'Perfil de integración para sincronizar recepciones de fruta desde SAP',
            'direccion' => 'entrada',
            'tipo_salida' => 'json',
            'source_adapter' => 'internal',
            'estado' => IntegrationProfileStatus::PUBLICADO,
            'activo' => true,
            'created_by' => $admin->id,
        ]);

        $version = IntegrationProfileVersion::create([
            'profile_id' => $profile->id,
            'version' => 1,
            'estado' => IntegrationProfileStatus::PUBLICADO->value,
            'inmutable' => true,
            'descripcion' => 'Versión inicial de producción',
            'published_at' => now(),
            'published_by' => $admin->id,
            'created_by' => $admin->id,
        ]);

        $profile->update(['current_version_id' => $version->id]);

        $inputFields = [
            ['clave' => 'n_recepcion', 'etiqueta' => 'Número Recepción', 'tipo_dato' => IntegrationFieldType::STRING, 'obligatorio' => true, 'posicion' => 0],
            ['clave' => 'rut_productor', 'etiqueta' => 'RUT Productor', 'tipo_dato' => IntegrationFieldType::STRING, 'obligatorio' => true, 'posicion' => 1],
            ['clave' => 'nombre_productor', 'etiqueta' => 'Nombre Productor', 'tipo_dato' => IntegrationFieldType::STRING, 'obligatorio' => true, 'posicion' => 2],
            ['clave' => 'especie', 'etiqueta' => 'Especie', 'tipo_dato' => IntegrationFieldType::STRING, 'obligatorio' => true, 'posicion' => 3],
            ['clave' => 'variedad', 'etiqueta' => 'Variedad', 'tipo_dato' => IntegrationFieldType::STRING, 'obligatorio' => false, 'posicion' => 4],
            ['clave' => 'kgs_brutos', 'etiqueta' => 'Kilos Brutos', 'tipo_dato' => IntegrationFieldType::DECIMAL, 'obligatorio' => true, 'posicion' => 5],
            ['clave' => 'kgs_netos', 'etiqueta' => 'Kilos Netos', 'tipo_dato' => IntegrationFieldType::DECIMAL, 'obligatorio' => true, 'posicion' => 6],
            ['clave' => 'fecha_recepcion', 'etiqueta' => 'Fecha Recepción', 'tipo_dato' => IntegrationFieldType::DATE, 'obligatorio' => true, 'posicion' => 7],
            ['clave' => 'n_guia', 'etiqueta' => 'Número Guía', 'tipo_dato' => IntegrationFieldType::STRING, 'obligatorio' => false, 'posicion' => 8],
            ['clave' => 'observaciones', 'etiqueta' => 'Observaciones', 'tipo_dato' => IntegrationFieldType::STRING, 'obligatorio' => false, 'posicion' => 9],
        ];

        foreach ($inputFields as $field) {
            IntegrationInputField::create([
                'profile_version_id' => $version->id,
                'clave' => $field['clave'],
                'etiqueta' => $field['etiqueta'],
                'tipo_dato' => $field['tipo_dato'],
                'obligatorio' => $field['obligatorio'],
                'posicion' => $field['posicion'],
                'activo' => true,
            ]);
        }

        $outputFields = [
            ['clave_externa' => 'RecepcionID', 'etiqueta' => 'ID Recepción', 'tipo_dato' => IntegrationFieldType::STRING, 'obligatorio' => true, 'posicion' => 0],
            ['clave_externa' => 'RutProductor', 'etiqueta' => 'RUT Productor', 'tipo_dato' => IntegrationFieldType::STRING, 'obligatorio' => true, 'posicion' => 1],
            ['clave_externa' => 'NombreProductor', 'etiqueta' => 'Nombre Productor', 'tipo_dato' => IntegrationFieldType::STRING, 'obligatorio' => true, 'posicion' => 2],
            ['clave_externa' => 'CodigoEspecie', 'etiqueta' => 'Código Especie', 'tipo_dato' => IntegrationFieldType::STRING, 'obligatorio' => true, 'posicion' => 3],
            ['clave_externa' => 'CodigoVariedad', 'etiqueta' => 'Código Variedad', 'tipo_dato' => IntegrationFieldType::STRING, 'obligatorio' => false, 'posicion' => 4],
            ['clave_externa' => 'KilosBrutos', 'etiqueta' => 'Kilos Brutos', 'tipo_dato' => IntegrationFieldType::DECIMAL, 'obligatorio' => true, 'posicion' => 5],
            ['clave_externa' => 'KilosNetos', 'etiqueta' => 'Kilos Netos', 'tipo_dato' => IntegrationFieldType::DECIMAL, 'obligatorio' => true, 'posicion' => 6],
            ['clave_externa' => 'FechaRecepcion', 'etiqueta' => 'Fecha Recepción', 'tipo_dato' => IntegrationFieldType::DATE, 'obligatorio' => true, 'posicion' => 7],
            ['clave_externa' => 'NumeroGuia', 'etiqueta' => 'Número Guía', 'tipo_dato' => IntegrationFieldType::STRING, 'obligatorio' => false, 'posicion' => 8],
            ['clave_externa' => 'Notas', 'etiqueta' => 'Notas', 'tipo_dato' => IntegrationFieldType::STRING, 'obligatorio' => false, 'posicion' => 9],
        ];

        foreach ($outputFields as $field) {
            IntegrationOutputField::create([
                'profile_version_id' => $version->id,
                'clave_externa' => $field['clave_externa'],
                'etiqueta' => $field['etiqueta'],
                'tipo_dato' => $field['tipo_dato'],
                'obligatorio' => $field['obligatorio'],
                'posicion' => $field['posicion'],
                'activo' => true,
            ]);
        }

        $rule1 = IntegrationRule::create([
            'profile_version_id' => $version->id,
            'tipo' => IntegrationRuleType::DIRECT->value,
            'nombre' => 'Mapeo directo RecepcionID',
            'configuracion' => ['source_field' => 'n_recepcion', 'target_field' => 'RecepcionID'],
            'orden' => 0,
            'obligatoria' => true,
            'politica_error' => IntegrationRuleErrorPolicy::STOP_RECORD->value,
            'activo' => true,
        ]);
        IntegrationRuleInput::create(['rule_id' => $rule1->id, 'clave_origen' => 'n_recepcion']);
        IntegrationRuleOutput::create(['rule_id' => $rule1->id, 'clave_destino' => 'RecepcionID']);

        $rule2 = IntegrationRule::create([
            'profile_version_id' => $version->id,
            'tipo' => IntegrationRuleType::DIRECT->value,
            'nombre' => 'Mapeo directo RUT Productor',
            'configuracion' => ['source_field' => 'rut_productor', 'target_field' => 'RutProductor'],
            'orden' => 1,
            'obligatoria' => true,
            'politica_error' => IntegrationRuleErrorPolicy::STOP_RECORD->value,
            'activo' => true,
        ]);
        IntegrationRuleInput::create(['rule_id' => $rule2->id, 'clave_origen' => 'rut_productor']);
        IntegrationRuleOutput::create(['rule_id' => $rule2->id, 'clave_destino' => 'RutProductor']);

        $rule3 = IntegrationRule::create([
            'profile_version_id' => $version->id,
            'tipo' => IntegrationRuleType::CONCATENATION->value,
            'nombre' => 'Concatenar nombre completo productor',
            'configuracion' => ['separator' => ' ', 'fields' => ['nombre_productor']],
            'orden' => 2,
            'obligatoria' => true,
            'politica_error' => IntegrationRuleErrorPolicy::LOG_WARNING->value,
            'activo' => true,
        ]);
        IntegrationRuleInput::create(['rule_id' => $rule3->id, 'clave_origen' => 'nombre_productor', 'alias' => 'nombre']);
        IntegrationRuleOutput::create(['rule_id' => $rule3->id, 'clave_destino' => 'NombreProductor']);

        $rule4 = IntegrationRule::create([
            'profile_version_id' => $version->id,
            'tipo' => IntegrationRuleType::CONDITIONAL->value,
            'nombre' => 'Validar kilos netos no superen brutos',
            'configuracion' => [
                'condition' => 'kgs_netos <= kgs_brutos',
                'true_action' => 'direct',
                'false_action' => 'warn',
            ],
            'orden' => 3,
            'obligatoria' => false,
            'politica_error' => IntegrationRuleErrorPolicy::LOG_WARNING->value,
            'activo' => true,
        ]);
        IntegrationRuleInput::create(['rule_id' => $rule4->id, 'clave_origen' => 'kgs_brutos']);
        IntegrationRuleInput::create(['rule_id' => $rule4->id, 'clave_origen' => 'kgs_netos']);
        IntegrationRuleOutput::create(['rule_id' => $rule4->id, 'clave_destino' => 'KilosBrutos']);
        IntegrationRuleOutput::create(['rule_id' => $rule4->id, 'clave_destino' => 'KilosNetos']);

        IntegrationProfile::create([
            'client_id' => $client2->id,
            'codigo' => 'INT-EXP-001',
            'nombre' => 'Exportación Calidad a API',
            'descripcion' => 'Exporta datos de control de calidad hacia API de clientes',
            'direccion' => 'salida',
            'tipo_salida' => 'csv',
            'source_adapter' => 'internal',
            'exporter' => 'csv',
            'estado' => IntegrationProfileStatus::BORRADOR,
            'activo' => true,
            'created_by' => $admin->id,
        ]);

        $this->command->info('Datos de ejemplo de integraciones creados correctamente');
    }
}
