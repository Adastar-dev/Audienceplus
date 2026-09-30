<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// dossiers.id_justiciable et dossiers.id_avocat : premier modèle des parties
// (un seul justiciable et un seul avocat par dossier), remplacé par la table
// parties_dossier (demandeur, défendeur et leurs avocats). Plus aucun code ne
// les lit ni ne les écrit.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['id_avocat', 'id_justiciable'] as $colonne) {
            if (Schema::hasColumn('dossiers', $colonne)) {
                Schema::table('dossiers', fn (Blueprint $table) => $table->dropConstrainedForeignId($colonne));
            }
        }
    }

    public function down(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->foreignId('id_justiciable')->nullable()->after('parties')->constrained('utilisateurs', 'id_utilisateur');
            $table->foreignId('id_avocat')->nullable()->after('id_justiciable')->constrained('utilisateurs', 'id_utilisateur');
        });
    }
};
