<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('casiers_judiciaires', function (Blueprint $table) {
            $table->id('id_casier');
            $table->foreignId('id_utilisateur')->constrained('utilisateurs', 'id_utilisateur')->cascadeOnDelete();
            $table->enum('resultat', ['VIERGE', 'NON_VIERGE'])->nullable();
            $table->string('qr_code')->nullable();
            $table->string('fichier_pdf')->nullable();
            $table->timestamp('date_demandee')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('casiers_judiciaires');
    }
};
