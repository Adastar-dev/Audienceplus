<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// La référence du casier (encodée dans son QR code) doit être unique.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('casiers_judiciaires', function (Blueprint $table) {
            $table->unique('qr_code');
        });
    }

    public function down(): void
    {
        Schema::table('casiers_judiciaires', function (Blueprint $table) {
            $table->dropUnique(['qr_code']);
        });
    }
};
