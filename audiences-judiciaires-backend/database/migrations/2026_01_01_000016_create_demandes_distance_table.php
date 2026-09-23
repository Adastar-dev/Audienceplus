<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes_distance', function (Blueprint $table) {
            $table->id('id_demande');
            $table->foreignId('id_audience')->constrained('audiences', 'id_audience')->cascadeOnDelete();
            $table->foreignId('id_utilisateur')->constrained('utilisateurs', 'id_utilisateur')->cascadeOnDelete();
            $table->text('motif');
            $table->enum('statut', ['EN_ATTENTE', 'APPROUVEE', 'REFUSEE'])->default('EN_ATTENTE');
            $table->text('reponse_greffier')->nullable();
            $table->timestamp('date_demande')->useCurrent();
            $table->timestamp('date_traitement')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_distance');
    }
};
