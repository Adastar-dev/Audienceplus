<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartieDossier extends Model
{
    protected $table = 'parties_dossier';

    protected $primaryKey = 'id_partie';

    protected $fillable = ['id_dossier', 'id_utilisateur', 'role_partie', 'represente_id_partie'];

    public function dossier()
    {
        return $this->belongsTo(Dossier::class, 'id_dossier', 'id_dossier');
    }

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'id_utilisateur', 'id_utilisateur');
    }

    public function represente()
    {
        return $this->belongsTo(self::class, 'represente_id_partie', 'id_partie');
    }
}
