<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Deux fonctionnalités demandées : le procureur doit pouvoir donner son avis
// sur un PV (rôle consultatif, comme son avis sur le dossier), et l'avocat
// doit pouvoir contester un PV une fois qu'il est clôturé (validé par le juge).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proces_verbaux', function (Blueprint $table) {
            $table->text('avis_procureur')->nullable()->after('commentaire_rejet');
            $table->foreignId('avis_procureur_par')->nullable()->after('avis_procureur')
                ->constrained('utilisateurs', 'id_utilisateur')->nullOnDelete();
            $table->timestamp('avis_procureur_date')->nullable()->after('avis_procureur_par');

            $table->text('contestation_avocat')->nullable()->after('avis_procureur_date');
            $table->foreignId('conteste_par')->nullable()->after('contestation_avocat')
                ->constrained('utilisateurs', 'id_utilisateur')->nullOnDelete();
            $table->timestamp('date_contestation')->nullable()->after('conteste_par');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE proces_verbaux MODIFY statut ENUM('EN_COURS', 'EN_VALIDATION', 'CLOTURE', 'CONTESTE') DEFAULT 'EN_COURS'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE proces_verbaux SET statut = 'CLOTURE' WHERE statut = 'CONTESTE'");
            DB::statement("ALTER TABLE proces_verbaux MODIFY statut ENUM('EN_COURS', 'EN_VALIDATION', 'CLOTURE') DEFAULT 'EN_COURS'");
        }

        Schema::table('proces_verbaux', function (Blueprint $table) {
            $table->dropConstrainedForeignId('avis_procureur_par');
            $table->dropConstrainedForeignId('conteste_par');
            $table->dropColumn(['avis_procureur', 'avis_procureur_date', 'contestation_avocat', 'date_contestation']);
        });
    }
};
