<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dossiers', function (Blueprint $table) {
            $table->id('id_dossier');
            $table->string('numero')->unique();
            $table->enum('type', [
                'DIVORCE', 'ADOPTION', 'RECTIFICATION_ACTE', 'CONTENTIEUX_MARIAGE',
                'FILIATION', 'GARDE_PENSION', 'TUTELLE', 'DECLARATION_ABSENCE_DECES',
                'CHANGEMENT_NOM', 'EMANCIPATION',
            ]);
            $table->enum('statut', ['EN_COURS', 'RENVOYE', 'JUGE', 'CLOTURE'])->default('EN_COURS');
            $table->string('parties');
            $table->foreignId('id_tribunal')->constrained('tribunaux', 'id_tribunal');
            $table->timestamp('date_creation')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dossiers');
    }
};
