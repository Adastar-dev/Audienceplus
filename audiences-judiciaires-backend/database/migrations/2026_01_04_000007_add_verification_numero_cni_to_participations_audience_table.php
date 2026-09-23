<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Complete la comparaison faciale (score_concordance) par une lecture OCR du
// numero visible sur la photo de CNI, comparee au numero declare a
// l'inscription (colonne cni de l'utilisateur). Meme raisonnement que le
// score facial : un indicateur pour le greffier/juge, jamais un blocage
// automatique - l'OCR se trompe facilement sur une photo prise au telephone.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participations_audience', function (Blueprint $table) {
            $table->string('numero_cni_detecte')->nullable()->after('score_concordance');
            $table->boolean('numero_cni_concorde')->nullable()->after('numero_cni_detecte');
        });
    }

    public function down(): void
    {
        Schema::table('participations_audience', function (Blueprint $table) {
            $table->dropColumn(['numero_cni_detecte', 'numero_cni_concorde']);
        });
    }
};
