<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proces_verbaux', function (Blueprint $table) {
            $table->longText('transcription_brute')->nullable()->after('contenu');
            $table->string('audio_path')->nullable()->after('transcription_brute');
        });
    }

    public function down(): void
    {
        Schema::table('proces_verbaux', function (Blueprint $table) {
            $table->dropColumn(['transcription_brute', 'audio_path']);
        });
    }
};
