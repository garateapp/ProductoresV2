<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // sqlite no requiere cambio (tipado flexible).
        if ($driver === 'sqlite') {
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE shifts MODIFY horas DECIMAL(5,2) NOT NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE shifts ALTER COLUMN horas TYPE numeric(5,2)');
            return;
        }

        if ($driver === 'sqlsrv') {
            DB::statement('ALTER TABLE shifts ALTER COLUMN horas decimal(5,2) NOT NULL');
            return;
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE shifts MODIFY horas TINYINT UNSIGNED NOT NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE shifts ALTER COLUMN horas TYPE integer');
            return;
        }

        if ($driver === 'sqlsrv') {
            DB::statement('ALTER TABLE shifts ALTER COLUMN horas tinyint NOT NULL');
            return;
        }
    }
};

