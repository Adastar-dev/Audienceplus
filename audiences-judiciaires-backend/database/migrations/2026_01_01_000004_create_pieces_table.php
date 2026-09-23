<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pieces', function (Blueprint $table) {
            $table->id('id_piece');
            $table->foreignId('id_dossier')->constrained('dossiers', 'id_dossier')->cascadeOnDelete();
            $table->foreignId('depose_par')->nullable()
                ->constrained('utilisateurs', 'id_utilisateur')->nullOnDelete();
            $table->string('nom');
            $table->string('type_fichier')->nullable();
            $table->string('chemin');
            $table->boolean('valide')->default(false);
            $table->timestamp('date_depot')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pieces');
    }
};
