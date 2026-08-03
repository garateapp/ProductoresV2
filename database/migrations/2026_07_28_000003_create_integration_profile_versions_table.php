<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_profile_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('integration_profiles')->cascadeOnDelete();
            $table->integer('version')->unsigned();
            $table->string('estado', 30)->default('borrador'); // borrador, en_pruebas, publicado, inactivo
            $table->boolean('inmutable')->default(false);
            $table->text('descripcion')->nullable();
            $table->json('snapshot_config')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['profile_id', 'version']);
        });

        Schema::table('integration_profiles', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('integration_profile_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_profile_versions');
    }
};
