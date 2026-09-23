<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participations_audience', function (Blueprint $table) {
            $table->id('id_participation');
            $table->foreignId('id_audience')->constrained('audiences', 'id_audience')->cascadeOnDelete();
            $table->foreignId('id_utilisateur')->constrained('utilisateurs', 'id_utilisateur')->cascadeOnDelete();
            $table->string('role_audience');
            $table->boolean('micro_actif')->default(false);
            $table->boolean('camera_active')->default(false);
            $table->boolean('present')->default(false);
            $table->boolean('admis')->default(false);
            $table->timestamps();

            $table->unique(['id_audience', 'id_utilisateur']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participations_audience');
    }
};
