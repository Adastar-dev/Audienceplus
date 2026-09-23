<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// L'avis du procureur (cas d'utilisation "Donner un avis sur le dossier",
// voir DCU) n'etait jusqu'ici jamais persiste : le bouton "Transmettre"
// cote frontend (procureur/Dossiers.jsx) ne faisait qu'un setState local,
// perdu au moindre rafraichissement de page - aucune colonne, aucun
// endpoint ne l'enregistrait reellement.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->text('avis_procureur')->nullable()->after('parties');
            $table->foreignId('avis_procureur_par')->nullable()
                ->after('avis_procureur')->constrained('utilisateurs', 'id_utilisateur');
            $table->timestamp('avis_procureur_date')->nullable()->after('avis_procureur_par');
        });
    }

    public function down(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('avis_procureur_par');
            $table->dropColumn(['avis_procureur', 'avis_procureur_date']);
        });
    }
};
