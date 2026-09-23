<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties_dossier', function (Blueprint $table) {
            $table->id('id_partie');
            $table->foreignId('id_dossier')->constrained('dossiers', 'id_dossier')->cascadeOnDelete();
            $table->foreignId('id_utilisateur')->constrained('utilisateurs', 'id_utilisateur')->cascadeOnDelete();
            $table->enum('role_partie', ['DEMANDEUR', 'DEFENDEUR']);

            $table->foreignId('represente_id_partie')->nullable()
                ->constrained('parties_dossier', 'id_partie')->nullOnDelete();

            $table->timestamps();

            $table->unique(['id_dossier', 'id_utilisateur']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties_dossier');
    }
};
