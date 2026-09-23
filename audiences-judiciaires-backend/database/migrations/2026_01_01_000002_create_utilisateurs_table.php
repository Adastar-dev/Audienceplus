<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utilisateurs', function (Blueprint $table) {
            $table->id('id_utilisateur');
            $table->string('nom');
            $table->string('email')->nullable()->unique();
            $table->string('mot_de_passe');
            $table->enum('role', [
                'JUGE', 'GREFFIER', 'PROCUREUR', 'AVOCAT',
                'JUSTICIABLE', 'ADMINISTRATEUR',
            ]);
            $table->string('telephone')->nullable()->unique();
            $table->string('cni')->nullable()->unique();
            $table->string('numero_barreau')->nullable();
            $table->boolean('identite_verifiee')->default(false);
            $table->foreignId('id_tribunal')->nullable()
                ->constrained('tribunaux', 'id_tribunal')->nullOnDelete();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utilisateurs');
    }
};
