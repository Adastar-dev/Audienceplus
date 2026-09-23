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
        Schema::table('participations_audience', function (Blueprint $table) {
            $table->dropColumn('score_concordance');

            $table->string('otp_code_hash')->nullable()->after('numero_cni_concorde');
            $table->timestamp('otp_expire_a')->nullable()->after('otp_code_hash');
            $table->unsignedTinyInteger('otp_tentatives')->default(0)->after('otp_expire_a');
            $table->boolean('identite_confirmee_otp')->default(false)->after('otp_tentatives');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('participations_audience', function (Blueprint $table) {
            $table->dropColumn(['otp_code_hash', 'otp_expire_a', 'otp_tentatives', 'identite_confirmee_otp']);
            $table->float('score_concordance')->nullable()->after('cni_photo_path');
        });
    }
};
