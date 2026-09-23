<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signatures', function (Blueprint $table) {
            $table->id('id_signature');
            $table->foreignId('id_utilisateur')->constrained('utilisateurs', 'id_utilisateur')->cascadeOnDelete();
            $table->enum('type_document', ['PROCES_VERBAL', 'CONVOCATION', 'PARTICIPATION']);
            $table->unsignedBigInteger('id_document_signe');
            $table->string('hash');
            $table->timestamp('date_signature')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signatures');
    }
};
