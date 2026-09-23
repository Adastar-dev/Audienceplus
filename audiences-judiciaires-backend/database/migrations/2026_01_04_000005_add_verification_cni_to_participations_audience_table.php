<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participations_audience', function (Blueprint $table) {
            $table->string('cni_photo_path')->nullable()->after('selfie_path');
            $table->float('score_concordance')->nullable()->after('cni_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('participations_audience', function (Blueprint $table) {
            $table->dropColumn(['cni_photo_path', 'score_concordance']);
        });
    }
};
