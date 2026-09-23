<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            // Procureur par defaut sur le dossier (supplence) : sert au suivi/notification,
            // n'importe quel procureur reste libre de consulter le dossier et de donner un
            // avis - voir Dossier::estAccessiblePar, inchange pour ce role.
            $table->foreignId('id_procureur')->nullable()->after('id_avocat')
                ->constrained('utilisateurs', 'id_utilisateur')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_procureur');
        });
    }
};
