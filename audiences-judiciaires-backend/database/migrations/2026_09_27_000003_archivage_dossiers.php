<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Archivage des dossiers jugés (par le juge, ou automatiquement après les
// accusés de réception de la décision) : nouveau statut ARCHIVE, et lien entre
// une notification et l'audience dont elle annonce la décision.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->enum('statut', ['EN_COURS', 'RENVOYE', 'JUGE', 'CLOTURE', 'ARCHIVE'])->default('EN_COURS')->change();
            $table->timestamp('date_archivage')->nullable();
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('id_audience')->nullable()->constrained('audiences', 'id_audience')->nullOnDelete();
            $table->timestamp('date_accuse_reception')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_audience');
            $table->dropColumn('date_accuse_reception');
        });

        Schema::table('dossiers', function (Blueprint $table) {
            $table->dropColumn('date_archivage');
            $table->enum('statut', ['EN_COURS', 'RENVOYE', 'JUGE', 'CLOTURE'])->default('EN_COURS')->change();
        });
    }
};
