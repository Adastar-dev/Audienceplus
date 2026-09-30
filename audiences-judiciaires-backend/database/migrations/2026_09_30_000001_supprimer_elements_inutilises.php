<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Nettoyage des éléments qui ne servaient plus :
// - participations_audience : restes de l'ancienne comparaison faciale
//   (selfie, photo de CNI et lecture du numéro par participation) ; la
//   vérification de la CNI se fait désormais sur le compte (utilisateurs) ;
// - signatures.image_path : image de signature manuscrite jamais envoyée par
//   l'interface, le scellement reposant sur l'empreinte SHA-256 ;
// - users, password_reset_tokens, sessions : tables par défaut de Laravel,
//   inutiles ici (comptes dans « utilisateurs », sessions en fichiers).
return new class extends Migration
{
    public function up(): void
    {
        $colonnes = array_values(array_filter(
            ['selfie_path', 'cni_photo_path', 'numero_cni_detecte', 'numero_cni_concorde'],
            fn ($c) => Schema::hasColumn('participations_audience', $c),
        ));
        if ($colonnes) {
            Schema::table('participations_audience', fn (Blueprint $table) => $table->dropColumn($colonnes));
        }

        if (Schema::hasColumn('signatures', 'image_path')) {
            Schema::table('signatures', fn (Blueprint $table) => $table->dropColumn('image_path'));
        }

        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }

    public function down(): void
    {
        Schema::table('participations_audience', function (Blueprint $table) {
            $table->string('cni_photo_path')->nullable();
            $table->string('numero_cni_detecte')->nullable();
            $table->boolean('numero_cni_concorde')->nullable();
        });

        Schema::table('signatures', function (Blueprint $table) {
            $table->string('image_path')->nullable();
        });
    }
};
