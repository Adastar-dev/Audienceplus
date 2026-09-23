<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Même principe que pour les demandes de comparution à distance : le
// greffier donne un avis sur une demande de report, et c'est le juge qui
// valide (ou non) cet avis pour rendre la décision définitive.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convocations', function (Blueprint $table) {
            $table->enum('avis_greffier', ['FAVORABLE', 'DEFAVORABLE'])->nullable()->after('statut');
            $table->dateTime('nouvelle_date_proposee')->nullable()->after('avis_greffier');
            $table->foreignId('avis_greffier_par')->nullable()->after('nouvelle_date_proposee')
                ->constrained('utilisateurs', 'id_utilisateur')->nullOnDelete();
            $table->timestamp('avis_greffier_date')->nullable()->after('avis_greffier_par');
            $table->foreignId('decide_par')->nullable()->after('reponse_greffier')
                ->constrained('utilisateurs', 'id_utilisateur')->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE convocations MODIFY statut ENUM('ENVOYEE', 'RECUE', 'CONFIRMEE', 'REPORT_DEMANDE', 'AVIS_GREFFIER_FAVORABLE', 'AVIS_GREFFIER_DEFAVORABLE', 'REPORT_APPROUVEE', 'REPORT_REFUSEE') DEFAULT 'ENVOYEE'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE convocations SET statut = 'REPORT_DEMANDE' WHERE statut IN ('AVIS_GREFFIER_FAVORABLE', 'AVIS_GREFFIER_DEFAVORABLE')");
            DB::statement("ALTER TABLE convocations MODIFY statut ENUM('ENVOYEE', 'RECUE', 'CONFIRMEE', 'REPORT_DEMANDE', 'REPORT_APPROUVEE', 'REPORT_REFUSEE') DEFAULT 'ENVOYEE'");
        }

        Schema::table('convocations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('avis_greffier_par');
            $table->dropConstrainedForeignId('decide_par');
            $table->dropColumn(['avis_greffier', 'nouvelle_date_proposee', 'avis_greffier_date']);
        });
    }
};
