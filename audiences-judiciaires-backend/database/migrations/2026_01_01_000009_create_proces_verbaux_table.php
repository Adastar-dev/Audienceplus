<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proces_verbaux', function (Blueprint $table) {
            $table->id('id_pv');
            $table->foreignId('id_audience')->unique()
                ->constrained('audiences', 'id_audience')->cascadeOnDelete();
            $table->longText('contenu')->nullable();
            $table->string('fichier_pdf')->nullable();
            $table->enum('statut', ['EN_COURS', 'EN_VALIDATION', 'CLOTURE'])->default('EN_COURS');
            $table->text('commentaire_rejet')->nullable();
            $table->timestamp('date_validation')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proces_verbaux');
    }
};
