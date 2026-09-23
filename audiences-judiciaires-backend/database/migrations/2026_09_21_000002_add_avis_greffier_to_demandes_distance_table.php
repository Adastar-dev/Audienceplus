<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Le greffier ne décide plus seul d'approuver/refuser une demande de
// comparution à distance : il donne un avis, et c'est le juge qui valide
// (ou non) cet avis pour rendre la décision définitive.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demandes_distance', function (Blueprint $table) {
            $table->enum('avis_greffier', ['FAVORABLE', 'DEFAVORABLE'])->nullable()->after('statut');
            $table->foreignId('avis_greffier_par')->nullable()->after('avis_greffier')
                ->constrained('utilisateurs', 'id_utilisateur')->nullOnDelete();
            $table->timestamp('avis_greffier_date')->nullable()->after('avis_greffier_par');
            $table->text('commentaire_juge')->nullable()->after('reponse_greffier');
            $table->foreignId('decide_par')->nullable()->after('commentaire_juge')
                ->constrained('utilisateurs', 'id_utilisateur')->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE demandes_distance MODIFY statut ENUM('EN_ATTENTE', 'AVIS_GREFFIER_FAVORABLE', 'AVIS_GREFFIER_DEFAVORABLE', 'APPROUVEE', 'REFUSEE') DEFAULT 'EN_ATTENTE'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE demandes_distance SET statut = 'EN_ATTENTE' WHERE statut IN ('AVIS_GREFFIER_FAVORABLE', 'AVIS_GREFFIER_DEFAVORABLE')");
            DB::statement("ALTER TABLE demandes_distance MODIFY statut ENUM('EN_ATTENTE', 'APPROUVEE', 'REFUSEE') DEFAULT 'EN_ATTENTE'");
        }

        Schema::table('demandes_distance', function (Blueprint $table) {
            $table->dropConstrainedForeignId('avis_greffier_par');
            $table->dropConstrainedForeignId('decide_par');
            $table->dropColumn(['avis_greffier', 'avis_greffier_date', 'commentaire_juge']);
        });
    }
};
