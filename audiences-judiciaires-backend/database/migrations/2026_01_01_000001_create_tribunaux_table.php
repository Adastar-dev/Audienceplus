<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tribunaux', function (Blueprint $table) {
            $table->id('id_tribunal');
            $table->string('nom');
            $table->string('ville');
            $table->string('adresse')->nullable();
            $table->string('telephone')->nullable();
            $table->string('email_contact')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tribunaux');
    }
};
