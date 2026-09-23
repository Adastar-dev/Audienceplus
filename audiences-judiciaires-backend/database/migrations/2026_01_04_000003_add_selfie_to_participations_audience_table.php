<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participations_audience', function (Blueprint $table) {
            $table->string('selfie_path')->nullable()->after('camera_active');
        });
    }

    public function down(): void
    {
        Schema::table('participations_audience', function (Blueprint $table) {
            $table->dropColumn('selfie_path');
        });
    }
};
