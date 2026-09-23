<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logs_activite', function (Blueprint $table) {
            $table->id('id_log');
            $table->foreignId('id_utilisateur')->nullable()
                ->constrained('utilisateurs', 'id_utilisateur')->nullOnDelete();
            $table->string('action');
            $table->string('adresse_ip')->nullable();
            $table->timestamp('date')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs_activite');
    }
};
