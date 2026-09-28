<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Rappels automatiques avant l'audience (48 heures et 2 heures avant) : la
// date d'envoi évite de prévenir deux fois les mêmes personnes.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audiences', function (Blueprint $table) {
            $table->timestamp('rappel_48h_le')->nullable();
            $table->timestamp('rappel_2h_le')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('audiences', function (Blueprint $table) {
            $table->dropColumn(['rappel_48h_le', 'rappel_2h_le']);
        });
    }
};
