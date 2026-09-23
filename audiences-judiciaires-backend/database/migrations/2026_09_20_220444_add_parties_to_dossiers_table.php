<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
    Schema::table('dossiers', function (Blueprint $table) {
        $table->foreignId('id_justiciable')->nullable()->after('parties')
            ->constrained('utilisateurs', 'id_utilisateur');
        $table->foreignId('id_avocat')->nullable()->after('id_justiciable')
            ->constrained('utilisateurs', 'id_utilisateur');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            //
        });
    }
};
