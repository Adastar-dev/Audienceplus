<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convocations', function (Blueprint $table) {
            $table->id('id_convocation');
            $table->foreignId('id_audience')->constrained('audiences', 'id_audience')->cascadeOnDelete();
            $table->foreignId('id_utilisateur')->constrained('utilisateurs', 'id_utilisateur')->cascadeOnDelete();
            $table->enum('canal', ['EMAIL', 'SMS', 'APPEL_VOCAL', 'IN_APP']);
            $table->enum('statut', ['ENVOYEE', 'RECUE', 'CONFIRMEE', 'REPORT_DEMANDE', 'REPORT_APPROUVEE', 'REPORT_REFUSEE'])->default('ENVOYEE');
            $table->text('motif_report')->nullable();
            $table->timestamp('date_envoi')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convocations');
    }
};
