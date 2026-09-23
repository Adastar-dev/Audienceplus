<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audiences', function (Blueprint $table) {
            $table->id('id_audience');
            $table->foreignId('id_dossier')->constrained('dossiers', 'id_dossier')->cascadeOnDelete();
            $table->foreignId('id_juge')->nullable()
                ->constrained('utilisateurs', 'id_utilisateur')->nullOnDelete();
            $table->foreignId('id_salle')->nullable()
                ->constrained('salles_virtuelles', 'id_salle')->nullOnDelete();
            $table->dateTime('date_heure');
            $table->enum('mode', ['PRESENTIEL', 'EN_LIGNE'])->default('PRESENTIEL');
            $table->enum('statut', ['PROGRAMMEE', 'EN_COURS', 'CLOTUREE', 'RENVOYEE', 'DELIBERE', 'RATEE'])->default('PROGRAMMEE');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audiences');
    }
};
