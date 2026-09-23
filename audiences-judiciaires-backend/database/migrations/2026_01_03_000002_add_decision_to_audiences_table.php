<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audiences', function (Blueprint $table) {
            $table->enum('type_decision', ['JUGEMENT', 'RENVOI', 'DELIBERE'])->nullable()->after('statut');
            $table->text('motif_decision')->nullable()->after('type_decision');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE audiences MODIFY statut ENUM('PROGRAMMEE', 'EN_COURS', 'CLOTUREE', 'RENVOYEE', 'DELIBERE') DEFAULT 'PROGRAMMEE'");
        }
    }

    public function down(): void
    {
        Schema::table('audiences', function (Blueprint $table) {
            $table->dropColumn(['type_decision', 'motif_decision']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE audiences MODIFY statut ENUM('PROGRAMMEE', 'EN_COURS', 'CLOTUREE', 'RENVOYEE') DEFAULT 'PROGRAMMEE'");
        }
    }
};
