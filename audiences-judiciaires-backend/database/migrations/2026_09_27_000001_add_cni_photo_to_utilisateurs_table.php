<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// La photo de la CNI est désormais déposée une fois, à l'inscription (ou à la
// première connexion pour un compte créé par l'administrateur), et consultée
// par l'administrateur avant de vérifier le compte.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utilisateurs', function (Blueprint $table) {
            $table->string('cni_photo_path')->nullable()->after('cni');
            $table->string('numero_cni_detecte')->nullable()->after('cni_photo_path');
            $table->boolean('numero_cni_concorde')->nullable()->after('numero_cni_detecte');
        });
    }

    public function down(): void
    {
        Schema::table('utilisateurs', function (Blueprint $table) {
            $table->dropColumn(['cni_photo_path', 'numero_cni_detecte', 'numero_cni_concorde']);
        });
    }
};
