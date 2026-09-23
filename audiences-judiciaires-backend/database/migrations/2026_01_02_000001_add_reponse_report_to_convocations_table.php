<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convocations', function (Blueprint $table) {
            $table->text('reponse_greffier')->nullable()->after('motif_report');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE convocations MODIFY statut ENUM('ENVOYEE', 'RECUE', 'CONFIRMEE', 'REPORT_DEMANDE', 'REPORT_APPROUVEE', 'REPORT_REFUSEE') DEFAULT 'ENVOYEE'");
        }
    }

    public function down(): void
    {
        Schema::table('convocations', function (Blueprint $table) {
            $table->dropColumn('reponse_greffier');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE convocations MODIFY statut ENUM('ENVOYEE', 'RECUE', 'CONFIRMEE', 'REPORT_DEMANDE') DEFAULT 'ENVOYEE'");
        }
    }
};
