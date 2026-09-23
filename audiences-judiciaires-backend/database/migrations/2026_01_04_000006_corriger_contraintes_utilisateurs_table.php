<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Corrige deux incoherences entre la validation applicative et le schema :
// - email etait NOT NULL en base alors que la validation d'inscription
//   l'autorise a etre absent (un justiciable peut n'avoir qu'un telephone) -
//   provoquait un crash 500 (contrainte NOT NULL violee) a l'inscription
//   sans email au lieu d'une erreur de validation propre.
// - telephone n'avait aucune contrainte d'unicite, alors que login() l'utilise
//   comme identifiant alternatif a l'email (orWhere) : deux comptes avec le
//   meme telephone rendaient la connexion ambigue.
// ALTER TABLE ... MODIFY est specifique a MySQL (utilise en prod) ; non
// execute sous SQLite, ou create_utilisateurs_table.php declare deja ces
// colonnes correctement pour les tests (meme convention que les migrations
// precedentes touchant des colonnes existantes).
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE utilisateurs MODIFY email VARCHAR(255) NULL');
            DB::statement('ALTER TABLE utilisateurs ADD UNIQUE unique_telephone (telephone)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE utilisateurs DROP INDEX unique_telephone');
            DB::statement('ALTER TABLE utilisateurs MODIFY email VARCHAR(255) NOT NULL');
        }
    }
};
