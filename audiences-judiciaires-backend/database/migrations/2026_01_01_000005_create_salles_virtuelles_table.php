<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salles_virtuelles', function (Blueprint $table) {
            $table->id('id_salle');
            $table->foreignId('id_tribunal')->constrained('tribunaux', 'id_tribunal')->cascadeOnDelete();
            $table->string('lien_jitsi');
            $table->string('code_acces')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salles_virtuelles');
    }
};
