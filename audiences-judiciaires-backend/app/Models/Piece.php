<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Piece extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_piece';

    protected $fillable = ['id_dossier', 'depose_par', 'nom', 'type_fichier', 'chemin', 'valide', 'date_depot'];

    protected $casts = [
        'valide' => 'boolean',
        'date_depot' => 'datetime',
    ];

    public function dossier()
    {
        return $this->belongsTo(Dossier::class, 'id_dossier', 'id_dossier');
    }

    public function deposant()
    {
        return $this->belongsTo(Utilisateur::class, 'depose_par', 'id_utilisateur');
    }
}
