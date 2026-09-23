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
        Schema::table('audiences', function (Blueprint $table) {
            // Sur meet.jit.si, le premier arrive dans la salle en devient moderateur :
            // les autres participants ne doivent pouvoir entrer qu'une fois le juge
            // effectivement connecte a la conference Jitsi.
            $table->boolean('juge_connecte')->default(false)->after('statut');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audiences', function (Blueprint $table) {
            $table->dropColumn('juge_connecte');
        });
    }
};
