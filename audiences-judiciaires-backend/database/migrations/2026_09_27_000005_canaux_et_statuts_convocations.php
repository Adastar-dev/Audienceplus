<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Convocations : seuls l'email et la notification sur la plateforme sont
// utilisés (SMS et appel vocal jamais implémentés ou abandonnés), et le statut
// RECUE n'était jamais attribué. Les anciennes convocations SMS passent en
// EMAIL (elles ont aussi été envoyées par email).
return new class extends Migration
{
    public function up(): void
    {
        DB::table('convocations')->whereIn('canal', ['SMS', 'APPEL_VOCAL'])->update(['canal' => 'EMAIL']);
        DB::table('convocations')->where('statut', 'RECUE')->update(['statut' => 'ENVOYEE']);

        Schema::table('convocations', function (Blueprint $table) {
            $table->enum('canal', ['EMAIL', 'IN_APP'])->default('EMAIL')->change();
            $table->enum('statut', [
                'ENVOYEE', 'CONFIRMEE', 'REPORT_DEMANDE', 'AVIS_GREFFIER_FAVORABLE',
                'AVIS_GREFFIER_DEFAVORABLE', 'REPORT_APPROUVEE', 'REPORT_REFUSEE',
            ])->default('ENVOYEE')->change();
        });
    }

    public function down(): void
    {
        Schema::table('convocations', function (Blueprint $table) {
            $table->enum('canal', ['EMAIL', 'SMS', 'APPEL_VOCAL', 'IN_APP'])->change();
            $table->enum('statut', [
                'ENVOYEE', 'RECUE', 'CONFIRMEE', 'REPORT_DEMANDE', 'AVIS_GREFFIER_FAVORABLE',
                'AVIS_GREFFIER_DEFAVORABLE', 'REPORT_APPROUVEE', 'REPORT_REFUSEE',
            ])->default('ENVOYEE')->change();
        });
    }
};
